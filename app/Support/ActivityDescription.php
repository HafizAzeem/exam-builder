<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityDescription
{
    public const LABELS = [
        'paper.created' => 'Created paper', 'paper.saved' => 'Saved paper',
        'admin.teachers.store' => 'Added teacher', 'admin.teachers.update' => 'Updated teacher details or permissions for', 'admin.teachers.destroy' => 'Deleted teacher',
        'admin.profile.update' => 'Updated school profile', 'profile.update' => 'Updated own profile', 'profile.destroy' => 'Deleted own account',
        'builder.store' => 'Created paper', 'editor.update' => 'Saved paper', 'papers.destroy' => 'Deleted paper', 'papers.duplicate' => 'Duplicated paper',
        'questions.store' => 'Added questions to the question bank', 'questions.update' => 'Updated question', 'questions.destroy' => 'Deleted question',
        'builder.ai.paste.store' => 'Submitted questions to Question Assistant', 'builder.ai.cancel' => 'Canceled Question Assistant request',
        'builder.ai.questions.update' => 'Edited a question in preview', 'builder.ai.questions.remove' => 'Removed a question from preview',
        'builder.ai.manual-question' => 'Added a question to preview', 'builder.ai.bank-questions' => 'Added bank questions to preview', 'builder.ai.add-to-paper' => 'Added reviewed questions to paper',
        'logout' => 'Signed out', 'login' => 'Signed in', 'password.update' => 'Changed own password',
    ];

    public static function target(Request $request): ?string
    {
        foreach (['teacher', 'paper', 'question', 'import'] as $key) {
            $value = $request->route($key);
            if ($value instanceof Model) {
                $label = $value->getAttribute('name') ?: $value->getAttribute('title');

                return mb_substr($label ?: ucfirst($key).' #'.$value->getKey(), 0, 160);
            }
            if (is_scalar($value) && ctype_digit((string) $value)) {
                return ucfirst($key).' #'.$value;
            }
        }
        $route = $request->route()?->getName();
        $field = $route === 'admin.teachers.store' ? 'name' : ($route === 'builder.store' ? 'title' : null);
        $value = $field ? $request->input($field) : null;

        return is_string($value) ? mb_substr($value, 0, 160) : null;
    }

    public static function present(ActivityLog $log): array
    {
        $meta = $log->meta ?? [];
        $action = $log->action;
        if ($action === 'request.mutated') {
            $action = $meta['route'] ?? self::legacyRoute($meta['path'] ?? '', $meta['method'] ?? '');
        }
        $label = self::LABELS[$action] ?? 'Submitted a change';
        if ($log->action === 'paper.printed') {
            $label = 'Opened paper for printing';
        }
        $target = $meta['target'] ?? (isset($meta['paper_id']) ? 'Paper #'.$meta['paper_id'] : null);
        $name = $meta['actor_name'] ?? $log->user?->name ?? 'Former school user';
        $role = $meta['actor_role'] ?? ($log->user?->hasRole('institution_admin') ? 'School admin' : ($log->user?->hasRole('teacher') ? 'Teacher' : 'School user'));

        return [
            'id' => $log->id, 'actor_name' => $name, 'actor_role' => $role,
            'description' => $label.($target ? ' '.$target : ''), 'action_label' => $label,
            'created_at' => $log->created_at, 'method' => $meta['method'] ?? null,
            'path' => $meta['path'] ?? null, 'ip_address' => $log->ip_address,
            'legacy' => ! isset($meta['actor_role']),
        ];
    }

    private static function legacyRoute(string $path, string $method): string
    {
        foreach (['/admin/teachers' => 'admin.teachers', '/questions' => 'questions', '/papers' => 'papers'] as $prefix => $route) {
            if ($path === $prefix || preg_match('~^'.preg_quote($prefix, '~').'/\d+$~', $path)) {
                return $route.'.'.match ($method) {
                    'DELETE' => 'destroy', 'PUT', 'PATCH' => 'update', default => 'store'
                };
            }
        }

        return $path === '/admin/profile' ? 'admin.profile.update' : 'request.mutated';
    }
}
