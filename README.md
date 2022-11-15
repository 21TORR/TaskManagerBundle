Task Manager Bundle
===================

A small wrapper around symfony/messenger for simplifying task management.


Installation
------------

First install this bundle:

```bash
composer require 21torr/task-manager
```

Then configure your queues in `config/packages/task_manager.yaml`:

```yaml
task_manager:
    queues:
        # queues sorted by priority. Highest priority at the top
        - app_very_urgent
        - app_urgent
        - app
```

While this bundle auto-detects all queue names, you should define them manually in your config, as otherwise the priority between these queues might be wrong.
