<?php

namespace ShippingModule\Services;

use ShippingModule\Repositories\ShippingRepository;

/**
 * ShippingService
 * Handles the core business logic calculation securely on the backend.
 * Protects logic from being tampered with by the frontend.
 */
class ShippingService {
    
    private $repository;

    public function __construct(ShippingRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Calculates the shipping cost based on the total cart value.
     * Evaluates Free Shipping logic and Standard Flat rates.
     * 
     * @param float $cartTotal
     * @return array Contains 'eligible_for_free', 'cost', 'message'
     */
    public function calculateShipping(float $cartTotal) {
        
        // 1. Fetch Current Global Settings
        $settings = $this->repository->getSettings();
        
        $isFreeShippingEnabled = filter_var($settings['free_shipping_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $minFreeShippingAmount = (float) ($settings['free_shipping_min_amount'] ?? 1000.00);
        $defaultFlatRate = (float) ($settings['default_flat_rate'] ?? 80.00);

        // 2. Base Validation Structure
        $response = [
            'shipping_cost' => $defaultFlatRate,
            'is_free' => false,
            'message' => '',
            'amount_needed_for_free' => 0
        ];

        // 3. Business Logic Execution
        if ($isFreeShippingEnabled) {
            if ($cartTotal > $minFreeShippingAmount) {
                // User crossed the threshold
                $response['shipping_cost'] = 0.00;
                $response['is_free'] = true;
                $response['message'] = "You are eligible for Free Shipping!";
            } else {
                // User is under the threshold, display how much more they need
                $difference = $minFreeShippingAmount - $cartTotal;
                $response['amount_needed_for_free'] = round($difference, 2);
                $response['message'] = "Add ₹" . number_format($difference, 2) . " more to get Free Shipping!";
            }
        }

        return $response;
    }

    /**
     * Calculates Final Total considering per-product shipping and taxes securely.
     */
    public function getFinalOrderTotals(float $cartTotal, array $cartItems = []) {
        $shippingCost = 0;
        
        foreach ($cartItems as $item) {
            // Ignore shipping for Virtual/Downloadable, flat per physical product line (Amazon/Flipkart model)
            if (isset($item['product_type']) && $item['product_type'] === 'physical') {
                $shippingCost += (float) ($item['shipping_cost'] ?? 0);
            }
        }

        $settings = $this->repository->getSettings();
        $taxPercentage = (float) ($settings['tax_on_shipping_percentage'] ?? 0);
        
        $shippingTaxAmount = 0;
        if ($taxPercentage > 0 && $shippingCost > 0) {
            $shippingTaxAmount = ($shippingCost * $taxPercentage) / 100;
        }

        // We can still use calculateShipping for messaging (e.g. Free Shipping threshold)
        // but for now the user wants the sum of per-product costs.
        $shippingCalculation = $this->calculateShipping($cartTotal);
        // If the calculated shipping is 0 (free shipping eligible), we keep it as 0?
        // Requirement 1 & 4 suggest the per-product cost is the source of truth.
        // If $shippingCost > 0 but global free shipping applies, we might want to override.
        // However, user said "Total Shipping = Sum of all product shipping costs".
        
        if ($shippingCalculation['is_free']) {
            $shippingCost = 0;
        }

        return [
            'cart_subtotal' => $cartTotal,
            'shipping_cost' => $shippingCost,
            'shipping_tax'  => $shippingTaxAmount,
            'total_shipping_block' => $shippingCost + $shippingTaxAmount,
            'grand_total'   => $cartTotal + $shippingCost + $shippingTaxAmount,
            'shipping_metadata' => array_merge($shippingCalculation, ['shipping_cost' => $shippingCost, 'is_free' => ($shippingCost == 0)])
        ];
    }

}
?>
