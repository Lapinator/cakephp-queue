<?php

namespace Queue\Controller\Admin;

use App\Controller\AppController;
use Cake\Http\Exception\NotFoundException;
use Queue\Queue\TaskFinder;

/**
 * @property \Queue\Model\Table\QueuedJobsTable $QueuedJobs
 * @property \Queue\Model\Table\QueueProcessesTable $QueueProcesses
 */
class QueueController extends AppController {

	/**
	 * @var string
	 */
	public $modelClass = 'Queue.QueuedJobs';

	/**
	 * Admin center.
	 * Manage queues from admin backend (without the need to open ssh console window).
	 *
	 * @return \Cake\Http\Response|null
	 */
	public function index() {
		$this->loadModel('Queue.QueueProcesses');
		$status = $this->QueueProcesses->status();

		$current = $this->QueuedJobs->getLength();
		$pendingDetails = $this->QueuedJobs->getPendingStats();
		$new = 0;
		foreach ($pendingDetails as $pendingDetail) {
			if ($pendingDetail['fetched'] || $pendingDetail['failed']) {
				continue;
			}
			$new++;
		}

		$data = $this->QueuedJobs->getStats();

		$taskFinder = new TaskFinder();
		$tasks = $taskFinder->allAppAndPluginTasks();

		$servers = $this->QueueProcesses->find()->distinct(['server'])->find('list', ['keyField' => 'server', 'valueField' => 'server'])->toArray();
		$this->set(compact('new', 'current', 'data', 'pendingDetails', 'status', 'tasks', 'servers'));
		$this->helpers[] = 'Tools.Format';
		$this->helpers[] = 'Tools.Time';
		$this->helpers[] = 'Tools.Text';
	}

	/**
	 * @param string|null $job
	 *
	 * @return \Cake\Http\Response
	 *
	 * @throws \Cake\Http\Exception\NotFoundException
	 */
	public function addJob($job = null) {
		$this->request->allowMethod('post');
		if (!$job) {
			throw new NotFoundException();
		}

		$this->QueuedJobs->createJob($job);

		$this->Flash->success('Job ' . $job . ' added');

		return $this->redirect(['action' => 'index']);
	}

	/**
	 * @param int|null $id
	 *
	 * @return \Cake\Http\Response
	 *
	 * @throws \Cake\Http\Exception\NotFoundException
	 */
	public function resetJob($id = null) {
		$this->request->allowMethod('post');
		if (!$id) {
			throw new NotFoundException();
		}

		$this->QueuedJobs->reset($id);

		$this->Flash->success('Job # ' . $id . ' re-added');

		return $this->redirect(['action' => 'index']);
	}

	/**
	 * @param int|null $id
	 *
	 * @return \Cake\Http\Response
	 */
	public function removeJob($id = null) {
		$this->request->allowMethod('post');
		$queuedJob = $this->QueuedJobs->get($id);

		$this->QueuedJobs->delete($queuedJob);

		$this->Flash->success('Job # ' . $id . ' deleted');

		return $this->redirect(['action' => 'index']);
	}

	/**
	 * @return \Cake\Http\Response|null
	 */
	public function processes() {
		$processes = $this->QueuedJobs->getProcesses();

		if ($this->request->is('post') && $this->request->getQuery('end')) {
			$pid = (int)$this->request->getQuery('end');
			$this->QueuedJobs->endProcess($pid);

			return $this->redirect(['action' => 'processes']);
		}
		if ($this->request->is('post') && $this->request->getQuery('kill')) {
			$pid = (int)$this->request->getQuery('kill');
			$this->QueuedJobs->terminateProcess($pid);

			return $this->redirect(['action' => 'processes']);
		}

		$this->loadModel('Queue.QueueProcesses');
		$terminated = $this->QueueProcesses->find()->where(['terminate' => true])->all()->toArray();

		$this->set(compact('terminated', 'processes'));
		$this->helpers[] = 'Shim.Configure';
	}

	/**
	 * Mark all failed jobs as ready for re-run.
	 *
	 * @return \Cake\Http\Response
	 */
	public function reset() {
		$this->request->allowMethod('post');
		$this->QueuedJobs->reset();

		$message = __d('queue', 'OK');
		$this->Flash->success($message);

		return $this->redirect(['action' => 'index']);
	}

	/**
	 * Truncate the queue list / table.
	 *
	 * @return \Cake\Http\Response
	 */
	public function hardReset() {
		$this->request->allowMethod('post');
		$this->QueuedJobs->truncate();

		$message = __d('queue', 'OK');
		$this->Flash->success($message);

		return $this->redirect(['action' => 'index']);
	}

}
