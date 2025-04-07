2.0.3
=====

* (improvement) Bump dependencies.
* (improvement) Require PHP 8.4.


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
