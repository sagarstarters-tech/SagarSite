<?php
/**
 * ProductRepository
 * All database operations for products.
 * Uses prepared statements only.
 */
class ProductRepository
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getAll(int $limit, int $offset): array
    {
        $rows   = [];
        $stmt   = $this->conn->prepare(
            "SELECT p.*, c.name as category_name
             FROM products p
             JOIN categories c ON p.category_id = c.id
             ORDER BY p.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    }

    public function getCount(): int
    {
        return (int)$this->conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getLastInsertId(): int
    {
        return (int)$this->conn->insert_id;
    }

    public function insert(array $data): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO products (name, slug, description, meta_description, category_id, price, stock, image, image_fit)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssssiidss',
            $data['name'], $data['slug'], $data['description'], $data['meta_description'],
            $data['category_id'], $data['price'], $data['stock'], $data['image'], $data['image_fit']
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function update(int $id, array $data): bool
    {
        if (!empty($data['image'])) {
            $stmt = $this->conn->prepare(
                "UPDATE products SET name=?, slug=?, description=?, meta_description=?, category_id=?, price=?, stock=?, image=?, image_fit=? WHERE id=?"
            );
            $stmt->bind_param(
                'ssssiidssi',
                $data['name'], $data['slug'], $data['description'], $data['meta_description'],
                $data['category_id'], $data['price'], $data['stock'], $data['image'], $data['image_fit'], $id
            );
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE products SET name=?, slug=?, description=?, meta_description=?, category_id=?, price=?, stock=?, image_fit=? WHERE id=?"
            );
            $stmt->bind_param(
                'ssssiisd' . 'i',
                $data['name'], $data['slug'], $data['description'], $data['meta_description'],
                $data['category_id'], $data['price'], $data['stock'], $data['image_fit'], $id
            );
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        try {
            $this->conn->query("DELETE FROM sm_queue WHERE product_id = $id AND status IN ('pending', 'scheduled', 'retry')");
        } catch (\Throwable $e) {}
        $stmt = $this->conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getGalleryImages(int $product_id): array
    {
        $rows   = [];
        $result = $this->conn->query("SELECT * FROM product_images WHERE product_id = $product_id");
        if ($result) while ($r = $result->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    public function getAllGalleryGrouped(): array
    {
        $grouped = [];
        $result  = $this->conn->query("SELECT * FROM product_images");
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $grouped[$r['product_id']][] = $r;
            }
        }
        return $grouped;
    }

    public function insertGalleryImage(int $product_id, string $image): bool
    {
        $stmt = $this->conn->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
        $stmt->bind_param('is', $product_id, $image);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getGalleryImageById(int $img_id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM product_images WHERE id=?");
        $stmt->bind_param('i', $img_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function deleteGalleryImage(int $img_id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM product_images WHERE id=?");
        $stmt->bind_param('i', $img_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getAllSeoGrouped(): array
    {
        $grouped = [];
        $result  = $this->conn->query("SELECT * FROM seo_metadata WHERE entity_type='product'");
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $grouped[$r['entity_id']] = $r;
            }
        }
        return $grouped;
    }
}
