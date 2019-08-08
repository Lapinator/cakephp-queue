<?php
namespace Queue\Test\TestCase\View\Helper;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Queue\Model\Entity\QueuedJob;
use Queue\View\Helper\QueueProgressHelper;

class QueueProgressHelperTest extends TestCase {

	/**
	 * @var array
	 */
	public $fixtures = [
		'plugin.queue.QueuedJobs',
	];

	/**
	 * @var \Queue\View\Helper\QueueProgressHelper
	 */
	protected $QueueProgressHelper;

	/**
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->QueueProgressHelper = new QueueProgressHelper(new View(null));
	}

	/**
	 * @return void
	 */
	public function testProgress() {
		$queuedJob = new QueuedJob([
			'progress' => 0.9999,
		]);
		$result = $this->QueueProgressHelper->progress($queuedJob);
		$this->assertTextContains('99%', $result);

		$queuedJob = new QueuedJob([
			'progress' => 0.0001,
		]);
		$result = $this->QueueProgressHelper->progress($queuedJob);
		$this->assertTextContains('1%', $result);

		$queuedJob = new QueuedJob([
			'progress' => 1.0,
		]);
		$result = $this->QueueProgressHelper->progress($queuedJob);
		$this->assertTextContains('100%', $result);

		$queuedJob = new QueuedJob([
			'progress' => 0.0,
		]);
		$result = $this->QueueProgressHelper->progress($queuedJob);
		$this->assertTextContains('0%', $result);
	}

	/**
	 * @return void
	 */
	public function testProgressBar() {
		$queuedJob = new QueuedJob([
			'progress' => 0.47,
		]);
		$result = $this->QueueProgressHelper->progressBar($queuedJob, 5);
		$this->assertTextContains('<span title="47%">', $result);

		$queuedJob = new QueuedJob([
			'progress' => null,
		]);
		$result = $this->QueueProgressHelper->progressBar($queuedJob, 5);
		$this->assertNull($result);
	}

	/**
	 * @return void
	 */
	public function testHtmlProgressBar() {
		$queuedJob = new QueuedJob([
			'progress' => 0.47,
		]);
		$result = $this->QueueProgressHelper->htmlProgressBar($queuedJob);
		$expected = '<progress value="47" max="100" title="47%"></progress>';
		$this->assertSame($expected, $result);

		$queuedJob = new QueuedJob([
			'progress' => 0.9999,
		]);
		// For IE9 and below
		$fallback = $this->QueueProgressHelper->progressBar($queuedJob, 10);
		$result = $this->QueueProgressHelper->htmlProgressBar($queuedJob, $fallback);
		$expected = '<progress value="99" max="100" title="99%"><span title="99%">█████████░</span></progress>';
		$this->assertSame($expected, $result);
	}

	/**
	 * @return void
	 */
	public function testProgressBarByStatistics() {
		$this->_needsConnection();

		/** @var \Queue\Model\Entity\QueuedJob $queuedJob */
		$queuedJob = TableRegistry::get('Queue.QueuedJobs')->newEntity([
			'job_type' => 'Foo',
			'created' => (new FrozenTime())->subHour(),
			'fetched' => (new FrozenTime())->subHour(),
			'completed' => (new FrozenTime())->subHour()->addMinutes(10),
		]);
		TableRegistry::get('Queue.QueuedJobs')->saveOrFail($queuedJob);

		$queuedJob->completed = null;
		$queuedJob->fetched = (new FrozenTime())->subMinute();

		$result = $this->QueueProgressHelper->progressBar($queuedJob, 5);
		$this->assertTextContains('<span title="10%">', $result);
	}

	/**
	 * @return void
	 */
	public function testTimeoutProgressBar() {
		$queuedJob = new QueuedJob([
			'created' => (new FrozenTime())->subHour(),
			'notbefore' => (new FrozenTime())->addHour(),
		]);

		$result = $this->QueueProgressHelper->timeoutProgressBar($queuedJob, 5);
		$this->assertTextContains('<span title="50%">', $result);
	}

	/**
	 * Helper method for skipping tests that need a real connection.
	 *
	 * @return void
	 */
	protected function _needsConnection() {
		$config = ConnectionManager::getConfig('test');
		$this->skipIf(strpos($config['driver'], 'Mysql') === false, 'Only Mysql is working yet for this.');
	}

}
