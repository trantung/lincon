<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DataExtractCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'data:extract';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Extract a CSV file from database.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	private function geo($area) {
		$endpoint = sprintf(
			'http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=%s&language=ja-JP',
			urlencode($area)
		);
		$response = file_get_contents($endpoint);
		$data = json_decode($response, true);
		$location = array_get($data, 'results.0.geometry.location');
		return $location;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$area = $this->argument('area');
		$n = $this->argument('n');
		$options = $this->argument('options');
		$options = $options ? explode(',', $options) : [];
		$location = $this->geo($area);
		$fields = ['service_id', 'name', 'address'];
		$rows = [];
		$rows = (new Office())->extractNearby($location['lat'], $location['lng'], $options, $n);
		$file = storage_path(). "/{$area}_{$n}.csv";
		$fh = fopen($file, 'w');
		fputcsv($fh, array_merge(['order'], $fields));
		foreach ($rows as $index => $row) {
			$ret = ['order' => $index + 1];
			foreach ($fields as $f) $ret[$f] = $row[$f];
			array_walk($ret, function(& $value, $key) {
				$value = iconv('UTF-8//IGNORE', 'SJIS//IGNORE', $value);
			});
			fputcsv($fh, $ret);
		}

		$this->info('Done. Saved at '. $file);
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('area', InputArgument::REQUIRED, 'UTF8 Area to extract'),
			array('n', InputArgument::REQUIRED, 'Number of rows'),
			array('options', InputArgument::OPTIONAL, 'Search options, comma list of ID'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			// array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);
	}

}
