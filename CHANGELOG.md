3.4.0 (unreleased)
=====

* (improvement) Require Symfony 8.1+
* (feature) Use [Symfonys native message deduplication](https://symfony.com/doc/current/messenger.html#message-deduplication)


3.3.2
=====

* (improvement) Add a note about requeueing a single task in development.


3.3.1
=====

* (bug) Fix invalid index definition.


3.3.0
=====

* (feature) Properly serialize task objects in task log.
* (deprecation) Deprecate `TaskLog::getTaskObject()`. Use `TaskDetailsNormalizer::deserializeTask()` instead.
* (improvement) Avoid redundant `COUNT` queries in `LogCleaner` by using the count of fetched IDs directly.
* (internal) Add tests for `TaskDetailsNormalizer`, `LogCleaner`, `TaskRegistry`, `RegisterTasksEvent`, `TaskManager`, `TaskLog`, and `TaskRun`.
* (improvement) Add `TaskLogModel::findByTaskId()`.


3.2.4
=====

* (bug) Fix stamps passed to `TaskManager::enqueue()` being silently dropped due to `Envelope::with()` being immutable.
* (improvement) Add database index on `time_queued` column of `task_manager_tasks` for faster log queries and cleanup.
* (internal) Remove unused `Paginator` wrapper in `TaskLogModel::getMostRecentEntries()`.
* (security) Replace `serialize()`/`unserialize()` with Symfony Serializer in `TaskDetailsNormalizer`. Add `TaskDetailsNormalizer::deserializeTask()` as replacement for the deprecated `TaskLog::getTaskObject()`.


3.2.3
=====

* (improvements) Bump dependencies.


3.2.2
=====

* (internal) Add `TaskManagerInternalTask` as internal marker interface.
* (improvement) Heavily reduce memory usage of clean outdated task log implementation.
* (improvement) Work on clean outdatet task log asynchronously in `app` transport.


3.2.1
=====

* (improvement) Rename internal schedule to `TaskManagerInternalSchedule`.
* (improvement) Remove code, that was deprecated in `2.x`.


3.2.0
=====

* (feature) Add `TaskScheduler`.
* (improvement) Update task ULID when redispatching in the scheduler.
* (improvement) Require PHP 8.5+


3.1.1
=====

* (bug) Fix invalid task run duration calculation.


3.1.0
=====

* (feature) Add option to hide internal tasks in task log model.


3.0.0
=====

* (improvement) Simplify task run duration calculation.
* (improvement) Transform exception to log entry, so that the worker doesn't constantly fail.
* (deprecation) Deprecate entity getters in favor of properties.
* (bc) Add explicit task `class` field.
* (bug) Add missing run integration for `DispatchAfterRunTaskHandler`.


2.3.2
=====

* (improvement) Skip `task_manager_internals` queue when checking for sync-queues.


2.3.1
=====

* (bug) Properly handle `DispatchAfterRunTask`.
* (improvement) Add `task_manager_internals` transport for internal messages.
* (improvement) Increase default number of handled tasks in `run-worker` command.


2.3.0
=====

* (improvement) Always show key of task in queue tasks command.
* (feature) Add `DispatchAfterRunTask` to be able to redispatch tasks after the given run. You can use it in the Scheduler to reliably redispatch tasks.


2.2.0
=====

* (feature) Add app validator to ensure, that all tasks are properly configured in the routing.


2.1.1
=====

* (improvement) Replace `.` with `:` in `TaskMetaData::getKey`.
* (improvement) Change column of `TaskRun::$taskLog` to be not nullable.
* (internal) Update `21torr/janus` and use `phpunit` instead of `simple-phpunit`.


2.1.0
=====

* (feature) Add `task-manager:run-worker` command as wrapper for Symfony messengers `consume` command.
* (improvement) Default to `limit` of 5 messages in run worker command, if no other limit is given.
* (improvement) Validate unique task ids to a specific format.
* (feature) Also purge log entries with a max number of entries.


2.0.3
=====

* (improvement) Bump dependencies.
* (improvement) Require PHP 8.4.
* (improvement) Add upcoming method in `ChainOutput` to be forward-compatible with changes in the interface.


2.0.2
=====

* (bug) Add missing dependency.
* (improvement) Bump required Doctrine version.


2.0.1
=====

* (improvement) Add `TaskLog::getTaskObject()`.


2.0.0
=====

* (bc) **This bundle now requires that all your messages extend from the `Task` base class.** 
* (bc) Change signature of `TaskManager::enqueue()` to only accept tasks and a list of stamps.
* (bc) Remove `RegisterTasksEvent::registerTask()` and replace it with `RegisterTasksEvent::register(Task $task)`.
* (feature) Add `TaskDirector` and `RunDirector` to better integrate and log your task runs.
* (feature) Add native `Task` event that encapsulates commonly used logic.
* (feature) Add `TaskLog` to store already finished tasks.


1.3.1
=====

* (improvement) Display `Task`'s key in `task-manager:queue` overview for easier identification.


1.3.0
=====

* (improvement) Temporarily allow PHP 8.1 again.
* (feature) Add infrastructure to collect `Task` definitions to be able to build UI for it, by using the `RegisterTasksEvent`.
* (feature) Add `task-manager:list-tasks` command.
* (feature) Add `task-manager:queue` command.


1.2.0
=====

* (improvement) Add support for Symfony 7.
* (improvement) Require Symfony 6.4 and PHP 8.3


1.1.0
=====

* (feature) Add `UniqueMessageInterface`.


1.0.0
=====

Initial Release `\o/`
