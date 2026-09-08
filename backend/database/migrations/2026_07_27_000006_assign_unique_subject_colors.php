<?php

declare(strict_types=1);

use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $used = [];

        Subject::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->each(function (Subject $subject) use (&$used): void {
                $current = strtolower((string) $subject->color);
                if ($current !== '' && ! in_array($current, $used, true)) {
                    $used[] = $current;
                    return;
                }

                $replacement = collect(Subject::COLOR_PALETTE)->first(
                    fn (string $color): bool => ! in_array(strtolower($color), $used, true),
                );
                if ($replacement !== null) {
                    $subject->updateQuietly(['color' => $replacement]);
                    $used[] = strtolower($replacement);
                }
            });
    }

    public function down(): void
    {
        // Previous duplicate color assignments cannot be reconstructed safely.
    }
};
