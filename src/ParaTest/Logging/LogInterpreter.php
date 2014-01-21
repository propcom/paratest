<?php namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader,
    ParaTest\Logging\JUnit\TestSuite,
    ParaTest\Logging\MetaProvider;

class LogInterpreter extends MetaProvider
{
    /**
     * A collection of Reader objects
     * to aggregate results from
     *
     * @var array
     */
    protected $readers = array();
	
	/**
	 * An array of root suites
     * @var array
     */
    protected $suites = array();
	
	/**
	 * An array of all suites
     * @var array
     */
    protected $allSuites = array();

    /**
     * Reset the array pointer of the internal
     * readers collection
     */
    public function rewind()
    {
        reset($this->readers);
    }

    /**
     * Add a new Reader to be included
     * in the final results
     *
     * @param Reader $reader
     * @return $this
     */
    public function addReader(Reader $reader)
    {
        $this->readers[] = $reader;
		
		$this->mergeSuites($reader->getSuites());
		
        return $this;
    }
	
	/**
     * Recursively merge multiple nested suites
     *
     * @param $parentSuites
	 * @param TestSuit $newParent
     */
	protected function mergeSuites($parentSuites, $newParent=null) {
	
		// Loop through parent's suites
		foreach($parentSuites as $path=>$suite) {
			// Create new blank suite, if path doesn't already exist
			if(!isset($this->allSuites[$path])) {
				$this->allSuites[$path] = new TestSuite($suite->name, 0, 0, 0, 0, 0);
				
				// Save root suite
				if(!$newParent) {
					$this->suites[$path] = $this->allSuites[$path];
				}
			}
			
			// Add suite to parent
			if($newParent && !isset($newParent->suites[$path])) {
				$newParent->suites[$path] = $this->allSuites[$path];
			}
			
			// Add cases to new suite
			foreach($suite->cases as $case) {
				$this->allSuites[$path]->cases[] = $case;
			}
			
			// Merge child suites
			$this->mergeSuites($suite->suites, $this->allSuites[$path]);
			
			// Update stats of new suite
			$this->allSuites[$path]->tests += $suite->tests;
			$this->allSuites[$path]->assertions += $suite->assertions;
			$this->allSuites[$path]->failures += $suite->failures;
			$this->allSuites[$path]->errors += $suite->errors;
			$this->allSuites[$path]->time += $suite->time;
			$this->allSuites[$path]->file = $suite->file;
		}
	}

    /**
     * Return all Reader objects associated
     * with the LogInterpreter
     *
     * @return Reader[]
     */
    public function getReaders()
    {
        return $this->readers;
    }

    /**
     * Returns true if total errors and failures
     * equals 0, false otherwise
     *
     * @return bool
     */
    public function isSuccessful()
    {
        $failures = $this->getTotalFailures();
        $errors = $this->getTotalErrors();
        return $failures === 0 && $errors === 0;
    }

    /**
     * Get all test case objects found within
     * the collection of Reader objects
     *
     * @return array
     */
    public function getCases()
    {
        $cases = array();
		foreach($this->allSuites as $suite) {
			$cases = array_merge($cases, $suite->cases);
		}
        return $cases;
    }

    /**
     * Flattens all cases into their respective suites
     * @return array $suites a collection of suites and their cases
     */
    public function flattenCases()
    {
		return $this->suites;
    }

    /**
     * Returns a value as either a float or int
     *
     * @param $property
     * @return float|int
     */
    protected function getNumericValue($property)
    {
        return ($property === 'time') 
               ? floatval($this->accumulate('getTotalTime'))
               : intval($this->accumulate('getTotal' . ucfirst($property)));
    }

    /**
     * Gets messages of a given type and
     * merges them into a single collection
     *
     * @param $type
     * @return array
     */
    protected function getMessages($type)
    {
        return $this->mergeMessages('get' . ucfirst($type));
    }

    /**
     * Flatten messages into a single collection
     * based on an accessor method
     *
     * @param $method
     * @return array
     */
    private function mergeMessages($method)
    {
        $messages = array();
        foreach($this->readers as $reader)
            $messages = array_merge($messages, $reader->$method());
        return $messages;
    }

    /**
     * Reduces a collection of readers down to a single
     * result based on an accessor
     *
     * @param $method
     * @return mixed
     */
    private function accumulate($method)
    {
        return array_reduce($this->readers, function($result, $reader) use($method){
            $result += $reader->$method();
            return $result;
        }, 0);
    }
}