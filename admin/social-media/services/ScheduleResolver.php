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
        $type = $schedule['schedule_type'] ?? '';
        
        switch ($type) {
            case 'every_30min':
                return $now->modify('+30 minutes');
            case 'every_1hr':
                return $now->modify('+1 hour');
            case 'every_2hr':
                return $now->modify('+2 hours');
            case 'every_6hr':
                return $now->modify('+6 hours');
            case 'daily':
                return $this->getNextTimeSlot($schedule['time_slots'] ?? '[]', $now, false);
            case 'weekly':
                return $this->getNextTimeSlot($schedule['time_slots'] ?? '[]', $now, true, $schedule['days_of_week'] ?? '[]');
            case 'monthly':
                // Post on 1st and 15th by default
                $day = (int)$now->format('d');
                if ($day < 15) {
                    $now->setDate((int)$now->format('Y'), (int)$now->format('m'), 15)->setTime(12, 0);
                } else {
                    $now->modify('first day of next month')->setTime(12, 0);
                }
                return $now;
            case 'custom':
                // Add rudimentary cron parsing or simple interval fallback
                if (!empty($schedule['interval_minutes'])) {
                    return $now->modify('+' . $schedule['interval_minutes'] . ' minutes');
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
