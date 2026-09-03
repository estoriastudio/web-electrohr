<?php

return [
    'meal_amount_per_day' => 120,
    'sunday_bonus_amount' => 50,
    'sunday_daily_rate_divisor' => 6,
    'absence_monthly_factor' => 4,
    'absence_month_divisor' => 30,
    'incentive_rates' => [
        'incentive' => ['A' => 0.33, 'B' => 0.18, 'C' => 0.11, 'D' => 0.05],
        'overtime' => ['A' => 0.33, 'B' => 0.18, 'C' => 0.11, 'D' => 0.05],
        'day_off_exchange' => ['A' => 0.20, 'B' => 0.12, 'C' => 0.05],
        'emergency' => ['A' => 0.30, 'B' => 0.24, 'C' => 0.10],
    ],
];