3.x to 4.0
==========

* `TaskLog::getTaskObject()` was removed. Use `TaskDetailsNormalizer::deserializeTask($log)` instead.
* `TaskLog::$ulid` was removed.


1.x to 2.0
==========

* All your messages need to extend `Task` now.
* `TaskManager::enqueue()` now only accepts `Task`s.
* `RegisterTasksEvent::registerTask()` was removed, you should use `RegisterTasksEvent::register(Task $task)`.
