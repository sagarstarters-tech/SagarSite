<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DateTime;

/**
 * Class ScheduleResolver
 * Resolves scheduling times for social media posts based on schedule configuration.
 */
class ScheduleResolver {

    /**
     * Gets the next posting time based on schedule type.
     *
     * @param array $schedule
     * @return DateTime|null
     */
    public function getNextPostTime(array $schedule): ?DateTime {
        $now = new DateTime();
        $startMode = $schedule['start_mode'] ?? '';
        $startTime = !empty($schedule['start_time']) ? $schedule['start_time'] : '09:00:00';
        if (strlen($startTime) === 5) $startTime .= ':00';
        $parts = explode(':', $startTime);
        $hour = isset($parts[0]) ? (int)$parts[0] : 9;
        $min = isset($parts[1]) ? (int)$parts[1] : 0;

        if ($startMode === 'once_weekly') {
            $next = clone $now;
            $next->modify('+7 days')->setTime($hour, $min, 0);
            return $next;
        } elseif ($startMode === 'once_monthly') {
            $next = clone $now;
            $next->modify('+1 month')->setTime($hour, $min, 0);
            return $next;
        } elseif ($startMode === 'once_daily') {
            $next = clone $now;
            $next->modify('+1 day')->setTime($hour, $min, 0);
            return $next;
        }

        $type = $schedule['schedule_type'] ?? '';
        
        switch ($type) {
            case 'every_5min':
                return $now->modify('+5 minutes');
            case 'every_15min':
                return $now->modify('+15 minutes');
            case 'every_30min':
                return $now->modify('+30 minutes');
            case 'every_1hr':
                return $now->modify('+1 hour');
            case 'every_2hr':
                return $now->modify('+2 hours');
            case 'every_6hr':
                return $now->modify('+6 hours');
            case 'daily':
                $next = clone $now;
                $next->modify('+1 day')->setTime($hour, $min, 0);
                return $next;
            case 'weekly':
                $next = clone $now;
                $next->modify('+7 days')->setTime($hour, $min, 0);
                return $next;
            case 'monthly':
                $next = clone $now;
                $next->modify('+1 month')->setTime($hour, $min, 0);
                return $next;
            case 'custom':
                if (!empty($schedule['interval_minutes'])) {
                    return $now->modify('+' . (int)$schedule['interval_minutes'] . ' minutes');
                }
                return $now->modify('+1 hour');
            default:
                return $now->modify('+1 hour');
        }
    }

    /**
     * Distributes N posts across available schedule slots.
     *
     * @param array $schedule
     * @param int $count
     * @return array Array of DateTime objects
     */
    public function distributePostTimes(array $schedule, int $count): array {
        $times = [];
        $current = new DateTime();
        
        for ($i = 0; $i < $count; $i++) {
            $next = $this->getNextPostTime($schedule);
            if ($next) {
                // To simplify, we'll just add intervals progressively
                // A complete robust logic requires advancing a virtual cursor.
                $times[] = clone $next;
                // Rough simulation of advancing time
                if (strpos($schedule['schedule_type'], 'every_') === 0) {
                     $schedule['mock_now'] = $next;
                }
            }
        }
        return $times;
    }

    /**
     * Gets the upcoming time slots based on the schedule.
     *
     * @param array $schedule
     * @param int $count
     * @return array
     */
    public function getUpcomingSlots(array $schedule, int $count = 10): array {
        // Mock implementation for upcoming slots.
        return $this->distributePostTimes($schedule, $count);
    }

    /**
     * Internal helper to find next specific time slot.
     */
    private function getNextTimeSlot(string $timeSlotsJson, DateTime $now, bool $checkDays, string $daysOfWeekJson = '[]'): ?DateTime {
        $slots = json_decode($timeSlotsJson, true);
        if (!is_array($slots) || empty($slots)) {
             return $now->modify('+1 day')->setTime(12, 0);
        }
        
        $days = json_decode($daysOfWeekJson, true);
        if (!is_array($days)) $days = [];

        // Simplified logic: just pick the first slot next day for now
        $next = clone $now;
        $next->modify('+1 day');
        $slot = $slots[0] ?? '12:00';
        $parts = explode(':', $slot);
        if (count($parts) >= 2) {
            $next->setTime((int)$parts[0], (int)$parts[1]);
        }
        
        return $next;
    }
}
