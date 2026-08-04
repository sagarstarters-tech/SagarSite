<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once '../includes/db_connect.php';
require_once '../includes/SeoRepository.php';
require_once 'core/AuthMiddleware.php';

// Ensure the user is logged in as an admin
AuthMiddleware::check($conn);

set_time_limit(0); // Allow large files to process

$seoRepo = new SeoRepository($conn);

$added = 0;
$updated = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    
    $file = $_FILES['import_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $rows = [];
    
    if ($ext === 'csv') {
        if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
            // Check for BOM and skip
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        } else {
            $errors[] = "Failed to open CSV file.";
        }
    } elseif (in_array($ext, ['xlsx', 'xls'])) {
        // Basic Native PHP XLSX parser using ZipArchive (no massive vendor libs req.)
        $zip = new ZipArchive;
        if ($zip->open($file['tmp_name']) === true) {
            $strings = [];
            if (($sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $sharedStrings = simplexml_load_string($sharedStringsXML);
                if ($sharedStrings) {
                    foreach ($sharedStrings->si as $val) {
                        if (isset($val->t)) {
                            $strings[] = (string)$val->t;
                        } elseif (isset($val->r)) {
                            $res = '';
                            foreach ($val->r as $r) { $res .= (string)$r->t; }
                            $strings[] = $res;
                        }
                    }
                }
            }
            
            $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXML) {
                $sheet = simplexml_load_string($sheetXML);
                foreach ($sheet->sheetData->row as $r) {
                    $rowData = [];
                    foreach ($r->c as $c) {
                        $colIndex = preg_replace('/[0-9]/', '', (string)$c['r']);
                        $colNum = 0;
                        for ($i = 0; $i < strlen($colIndex); $i++) {
                            $colNum = $colNum * 26 + (ord($colIndex[$i]) - 64);
                        }
                        $colNum -= 1; // 0-indexed column
                        
                        $val = (string)$c->v;
                        if (isset($c['t']) && (string)$c['t'] == 's') {
                            $val = $strings[(int)$val] ?? '';
                        }
                        
                        // Padding empty columns to maintain structure
                        while(count($rowData) < $colNum) {
                            $rowData[] = '';
                        }
                        $rowData[$colNum] = $val;
                    }
                    $rows[] = $rowData;
                }
            }
            $zip->close();
        } else {
            $errors[] = "Failed to parse XLSX file structure. Please save as CSV (Comma Delimited) and try again.";
        }
    } else {
        $errors[] = "Invalid file format. Only .CSV and .XLSX are allowed.";
    }
    
    // Process Extracted Rows
    if (!empty($rows)) {
        // Discard Header
        $header = array_shift($rows);
        
        // Cache Category Names Locally (O(1) resolution to prevent N+1 Queries)
        $cats = [];
        $c_res = $conn->query("SELECT id, name FROM categories");
        while($c = $c_res->fetch_assoc()) {
            $cats[strtolower(trim($c['name']))] = $c['id'];
        }
        
        foreach ($rows as $index => $row) {
            if (empty(array_filter($row))) continue; // Skip entirely blank rows
            
            // Map row to headers to avoid undefined offsets
            $id = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            $slug = trim($row[2] ?? '');
            $category_name = trim($row[4] ?? '');
            $description = trim($row[7] ?? '');
            $price = trim($row[9] ?? '');
            $stock = trim($row[11] ?? '0');
            $image = trim($row[13] ?? '');
            $gallery = trim($row[14] ?? '');
            $seo_title = trim($row[17] ?? '');
            $seo_desc = trim($row[18] ?? '');
            $seo_keywords = trim($row[19] ?? '');
            
            if (empty($name) || $price === '') {
                $errors[] = "Row " . ($index + 2) . ": Skipped. Product Name or Price is missing.";
                continue;
            }
            
            if (!is_numeric($price)) {
                $errors[] = "Row " . ($index + 2) . ": Skipped. Price must be a valid number.";
                continue;
            }
            
            if (empty($slug)) {
                $slug = time() . '-' . substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), 0, 50);
            }
            
            // Resolve Category ID
            $cat_id = 0;
            if (!empty($category_name)) {
                $c_key = strtolower($category_name);
                if (isset($cats[$c_key])) {
                    $cat_id = $cats[$c_key];
                } else {
                    $safe_cname = $conn->real_escape_string($category_name);
                    $cslug = strtolower(str_replace(' ', '-', preg_replace('/[^a-z0-9-]+/', '-', $category_name)));
                    $conn->query("INSERT INTO categories (name, slug) VALUES ('$safe_cname', '$cslug')");
                    $cat_id = $conn->insert_id;
                    $cats[$c_key] = $cat_id;
                }
            } else {
                if (!empty($cats)) {
                    $cat_id = reset($cats); // Default to first categorical collection
                }
            }
            
            $name_esc = $conn->real_escape_string($name);
            $slug_esc = $conn->real_escape_string($slug);
            $desc_esc = $conn->real_escape_string($description);
            $price_esc = floatval($price);
            $stock_esc = intval($stock);
            $clean_image = preg_replace('#^(assets/images/|uploads/|/)+#i', '', $image);
            $dummies = ['placeholder.svg', 'placeholder.png', 'no-image.png', 'no-image.jpg', 'null', 'undefined'];
            if (in_array(strtolower($clean_image), $dummies)) {
                $clean_image = '';
            }
            $image_esc = $conn->real_escape_string($clean_image);
            
            $is_update = false;
            $product_id = 0;
            
            // Check Update Priority: 1. ID Match, 2. Slug Match
            if (!empty($id) && is_numeric($id)) {
                $check = $conn->query("SELECT id FROM products WHERE id = " . intval($id));
                if ($check->num_rows > 0) {
                    $is_update = true;
                    $product_id = intval($id);
                }
            }
            if (!$is_update && !empty($slug)) {
                $check = $conn->query("SELECT id FROM products WHERE slug = '$slug_esc'");
                if ($check->num_rows > 0) {
                    $is_update = true;
                    $product_id = $check->fetch_assoc()['id'];
                }
            }
            
            if ($is_update) {
                // Determine if Image Field specifically was overridden in import
                $image_update_clause = "";
                if (!empty($clean_image)) {
                    $image_update_clause = ", image='$image_esc'";
                }
                
                $sql = "UPDATE products SET 
                            name='$name_esc', 
                            slug='$slug_esc', 
                            description='$desc_esc', 
                            category_id=$cat_id, 
                            price=$price_esc, 
                            stock=$stock_esc 
                            {$image_update_clause}
                        WHERE id=$product_id";
                
                if ($conn->query($sql)) {
                    $updated++;
                } else {
                    $errors[] = "Row " . ($index + 2) . ": Database Error during UPDATE.";
                }
            } else {
                $sql = "INSERT INTO products (name, slug, description, category_id, price, stock, image) 
                        VALUES ('$name_esc', '$slug_esc', '$desc_esc', $cat_id, $price_esc, $stock_esc, '$image_esc')";
                if ($conn->query($sql)) {
                    $added++;
                    $product_id = $conn->insert_id;
                } else {
                    $errors[] = "Row " . ($index + 2) . ": Database Error during INSERT.";
                }
            }
            
            // Map Metadata upon success
            if ($product_id > 0) {
                $seoRepo->saveMetadata([
                    'entity_type' => 'product',
                    'entity_id' => $product_id,
                    'meta_title' => $seo_title,
                    'meta_description' => $seo_desc,
                    'focus_keyword' => $seo_keywords
                ]);
                
                if (!empty($gallery)) {
                    // SAFE MERGE: Only add new gallery images, never delete existing ones.
                    // Fetch existing images for this product to avoid duplicate inserts.
                    $existing_gal_q = $conn->query("SELECT image FROM product_images WHERE product_id = $product_id");
                    $existing_gal_images = [];
                    if ($existing_gal_q) {
                        while ($eg = $existing_gal_q->fetch_assoc()) {
                            $existing_gal_images[] = strtolower(trim($eg['image']));
                        }
                    }
                    $g_imgs = explode(',', $gallery);
                    foreach($g_imgs as $g) {
                        $clean_g = preg_replace('#^(assets/images/|uploads/|/)+#i', '', trim($g));
                        if (!empty($clean_g) && !in_array(strtolower($clean_g), $dummies)) {
                            // Only insert if not already in the gallery
                            if (!in_array(strtolower($clean_g), $existing_gal_images)) {
                                $clean_g_esc = $conn->real_escape_string($clean_g);
                                $conn->query("INSERT INTO product_images (product_id, image) VALUES ($product_id, '$clean_g_esc')");
                                $existing_gal_images[] = strtolower($clean_g); // Prevent duplicates within same import row
                            }
                        }
                    }
                }
            }
        }
    }
    
    $_SESSION['import_success'] = "Import completed! Successfully Added: <strong>$added</strong> | Updated: <strong>$updated</strong>";
    if (!empty($errors)) {
        $_SESSION['import_errors'] = $errors;
    }
    
    header("Location: manage_products.php?import=complete");
    exit;
} else {
    header("Location: manage_products.php");
    exit;
}
?>
