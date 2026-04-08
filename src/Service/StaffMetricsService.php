<?php

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;

class StaffMetricsService
{
    public function __construct(private OrderRepository $orderRepository)
    {
    }

    /**
     * @return array<string, int>
     */
    public function getNavigationMetrics(): array
    {
        return [
            'fulfillment_count' => $this->orderRepository->countByStatuses([
                Order::STATUS_PAID,
                Order::STATUS_PROCESSING,
            ]),
            'ready_to_ship_count' => $this->orderRepository->countByStatus(Order::STATUS_READY_TO_SHIP),
            'returns_count' => $this->orderRepository->countByStatus(Order::STATUS_REFUNDED),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getQueueSummary(): array
    {
        $pending = $this->orderRepository->countByStatus(Order::STATUS_PAID);
        $inProgress = $this->orderRepository->countByStatus(Order::STATUS_PROCESSING);

        return [
            'pending' => $pending,
            'in_progress' => $inProgress,
            'ready_to_ship' => $this->orderRepository->countByStatus(Order::STATUS_READY_TO_SHIP),
            'shipped_today' => $this->orderRepository->countShippedToday(),
            'refunded' => $this->orderRepository->countByStatus(Order::STATUS_REFUNDED),
            'total_queue' => $pending + $inProgress,
        ];
    }

    /**
     * @return array<int, Order>
     */
    public function getPriorityOrders(int $limit = 8): array
    {
        return $this->orderRepository->findPendingFulfillment($limit);
    }

    /**
     * @return array<int, Order>
     */
    public function getReadyToShipOrders(int $limit = 8): array
    {
        return $this->orderRepository->findReadyToShip($limit);
    }

    /**
     * @return array<int, Order>
     */
    public function getRefundedOrders(int $limit = 20): array
    {
        return $this->orderRepository->findBy(
            ['status' => Order::STATUS_REFUNDED],
            ['updatedAt' => 'DESC', 'id' => 'DESC'],
            $limit
        );
    }
}
