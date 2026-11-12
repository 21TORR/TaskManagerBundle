4.x to 5.0
==========

* Wait time and memory usage are now stored per task run. You need to run migrations or upgrade the DB.
* `TaskIdentifier` was removed. The task id of a message is available via the `TaskIdStamp` of its envelope.


3.x to 4.0
==========

* `TaskLog::getTaskObject()` was removed. Use `TaskDetailsNormalizer::deserializeTask($log)` instead.
* `TaskLog::$ulid` was removed.
* `Task` has no constructor anymore, so you need to remove your `parent::__construct()` calls.


1.x to 2.0
==========

* All your messages need to extend `Task` now.
* `TaskManager::enqueue()` now only accepts `Task`s.
* `RegisterTasksEvent::registerTask()` was removed, you should use `RegisterTasksEvent::register(Task $task)`.
