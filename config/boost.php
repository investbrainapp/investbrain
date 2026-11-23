<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Skills Paths
    |--------------------------------------------------------------------------
    |
    | Boost writes skills straight into .ai/skills, and .claude/skills is a
    | committed symlink to that directory. Every other Boost option falls
    | back to the package defaults, which are merged in automatically.
    |
    */

    'agents' => [
        'claude_code' => [
            'skills_path' => '.ai/skills',
        ],
    ],

];
