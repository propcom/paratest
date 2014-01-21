<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\MetaProvider;

class Reader extends MetaProvider
{
    /**
     * @var \SimpleXMLElement
     */
    protected $xml;

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
     * @var string
     */
    protected $logFile;

    public function __construct($logFile) 
    {
        if(!file_exists($logFile))
            throw new \InvalidArgumentException("Log file $logFile does not exist");

        $this->logFile = $logFile;
        if (filesize($logFile) == 0) {
            throw new \InvalidArgumentException("Log file $logFile is empty. This means a PHPUnit process has crashed.");
        }
        $this->xml = simplexml_load_file($this->logFile);
		
        $this->suites = $this->initSuites($this->xml);
    }

    /**
     * Return the Reader's collection
     * of all test suites
     *
     * @return array
     */
    public function getAllSuites()
    {
        return $this->allSuites;
    }
	
	/**
     * Return the Reader's collection
     * of root test suites
     *
     * @return array
     */
    public function getSuites()
    {
        return $this->suites;
    }

    /**
     * Return an array that contains
     * each suite's instant feedback. Since
     * logs do not contain skipped or incomplete
     * tests this array will contain any number of the following
     * characters: .,F,E
     *
     * @return array
     */
    public function getFeedback()
    {
        $feedback = array();
		
        foreach($this->allSuites as $suite) {
            foreach($suite->cases as $case) {
                if($case->failures) $feedback[] = 'F';
                else if ($case->errors) $feedback[] = 'E';
                else $feedback[] = '.';
            }
        }
        return $feedback;
    }

    /**
     * Remove the JUnit xml file
     */
    public function removeLog()
    {
        unlink($this->logFile);
    }
	
    /**
     * Recursive function to initialise suites
     */
    protected function initSuites($rootNode, $parentPath='')
    {
		$suites = [];
		
		foreach($rootNode->testsuite as $node) {
			$path = $parentPath . '\\' . $node['name'];
			
			$suite = \ParaTest\Logging\JUnit\TestSuite::suiteFromNode($node);
			
			$suites[$path] = $suite;
			$this->allSuites[$path] = $suite;
			
			// Create test cases
			foreach($node->testcase as $node) {
				$case = \ParaTest\Logging\JUnit\TestCase::caseFromNode($node);
				$suite->cases[] = $case;
			}
		
			// Create nested suites
			$suite->suites = $this->initSuites($node, $path);
		}
		
		return $suites;
    }
}
