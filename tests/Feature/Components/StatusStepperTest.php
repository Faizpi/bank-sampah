<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class StatusStepperTest extends TestCase
{
    public function test_stepper_keeps_future_stages_visible_and_marks_passed_stages_with_checks(): void
    {
        $steps = [
            ['title' => 'Diajukan', 'icon' => 'file-check', 'statuses' => ['pending']],
            ['title' => 'Disetujui', 'icon' => 'clipboard-check', 'statuses' => ['approved']],
            ['title' => 'Selesai', 'icon' => 'package-check', 'statuses' => ['completed']],
        ];
        $history = [
            ['new_status' => 'pending', 'occurred_at' => '2026-08-10 10:00:00'],
            ['new_status' => 'approved', 'occurred_at' => '2026-08-10 10:15:00'],
        ];

        $html = Blade::render(
            '<x-ui.status-stepper :steps="$steps" current-status="approved" :history="$history" />',
            compact('steps', 'history'),
        );

        self::assertSame(1, substr_count($html, 'data-step-state="complete"'));
        self::assertSame(1, substr_count($html, 'data-step-state="current"'));
        self::assertSame(1, substr_count($html, 'data-step-state="upcoming"'));
        self::assertStringContainsString('data-step-state="upcoming"', $html);
        self::assertStringContainsString('Diajukan', $html);
        self::assertStringContainsString('Disetujui', $html);
        self::assertStringContainsString('Belum dimulai', $html);
    }

    public function test_terminal_step_does_not_make_future_stages_look_completed(): void
    {
        $steps = [
            ['title' => 'Diajukan', 'icon' => 'file-check', 'statuses' => ['pending']],
            ['title' => 'Disetujui', 'icon' => 'clipboard-check', 'statuses' => ['approved']],
            ['title' => 'Selesai', 'icon' => 'package-check', 'statuses' => ['completed']],
        ];
        $history = [
            ['new_status' => 'pending', 'occurred_at' => '2026-08-10 10:00:00'],
            ['new_status' => 'rejected', 'occurred_at' => '2026-08-10 10:15:00'],
        ];

        $html = Blade::render(
            '<x-ui.status-stepper :steps="$steps" current-status="rejected" :history="$history" :terminal-step="$terminalStep" />',
            compact('steps', 'history') + ['terminalStep' => ['status' => 'rejected', 'title' => 'Ditolak']],
        );

        self::assertSame(1, substr_count($html, 'data-step-state="complete"'));
        self::assertSame(2, substr_count($html, 'data-step-state="upcoming"'));
        self::assertSame(1, substr_count($html, 'data-step-state="error"'));
        self::assertStringContainsString('Ditolak', $html);
    }
}
