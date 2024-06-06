1.x to 2.0
==========

* All your messages need to extend `Task` now.
* `TaskManager::enqueue()` now only accepts `Task`s.
* `RegisterTasksEvent::registerTask()` was removed, you should use `RegisterTasksEvent::register(Task $task)`.
