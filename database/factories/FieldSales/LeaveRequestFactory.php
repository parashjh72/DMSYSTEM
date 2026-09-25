<?php

namespace Database\Factories\FieldSales;

use App\Models\FieldSales\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $from = now(config('field_sales.timezone'))->addDays(3)->startOfDay();

        return [
            'user_id' => User::factory(),
            'from_date' => $from->toDateString(),
            'to_date' => $from->toDateString(),
            'leave_type' => 'casual',
            'half_day' => false,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'reviewed_at' => now()]);
    }
}
