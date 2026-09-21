<?php

use App\Models\AIImport;
use App\Models\AIPaperCollection;
use App\Models\SavedPaper;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('ai-import.{import}', function (User $user, AIImport $import): bool {
    return $user->can('view', $import);
});

Broadcast::channel('paper-collection.{collection}', function (User $user, AIPaperCollection $collection): bool {
    return $user->can('view', $collection);
});

Broadcast::channel('paper.{paper}', function (User $user, SavedPaper $paper): bool {
    return (int) $user->institution_id === (int) $paper->institution_id;
});
