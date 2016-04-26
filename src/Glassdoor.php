<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace JobBrander\Jobs\Client\Providers;

use JobBrander\Jobs\Client\Job;
use JobBrander\Jobs\Client\Collection;
//require_once '../../jobs-common/src/Providers/AbstractProvider.php';
/**
 * Description of Glassdoor
 *
 * @author Alejandro
 */
class Glassdoor extends AbstractProvider{

    protected $results = null;
     /**
     * Map of setter methods to query parameters
     *
     * @var array
     */
    protected $queryMap = [
        'setPublisher' => 'publisher',
        'setVersion' => 'v',
        'setFormat' => 'format',
        'setKeyword' => 'q',
        'setLocation' => 'l',
        //'setSort' => 'sort',
        'setRadius' => 'radius',
        'setSiteType' => 'st',
        'setJobType' => 'jobType',
        'setPage' => 'pn',
        'setCount' => 'ps',
        'setDaysBack' => 'fromAge',
        //'setHighlight' => 'highlight',
        //'filterDuplicates' => 'filter',
        //'includeLatLong' => 'latlong',
        //'setChannel' => 'chnl',
        'setUserIp' => 'userip',
        'setUserAgent' => 'useragent',
        'setCity' => "city",
        'setState' => 'state',
        'setCountry' => 'country',
        'setMinRating' => 'minRating',
        'setJobScope' => 'js',
        //'getLocation' => 'l',
    ];

    /**
     * Query params
     *
     * @var array
     */
    protected $queryParams = [
        'publisher' => null,
        'v' => '1.1',
        'format' => null,
        'q' => null,
        'l' => null,
        'sort' => null,
        'radius' => null,
        'st' => null,
        'jt' => null,
        'pn' => 1,
        'ps' => 20,
        'fromage' => null,
        'highlight' => null,
        'filter' => null,
        'latlong' => null,
        'co' => null,
        'chnl' => null,
        'userip' => null,
        'useragent' => null,
        't.p' => null,
        't.k' => null,
        'format' => 'json',
        'action' => 'job',
    ];

    /**
     * Job defaults
     *
     * @var array
     */
 protected $jobDefaults = ['jobTitle','location','source',
        'date','jobViewUrl','jobListingId'
    ];
    
    
    
    public function __construct($parameters = [])
    {
        parent::__construct($parameters);
        
        $this->addDefaultUserInformationToParameters($parameters);
        if(array_key_exists("partnerId", $parameters)){
            $val = $parameters['partnerId'];
            unset($parameters['partnerId']);
            $this->setPartnerId($val);
        }
        if(array_key_exists("apiKey", $parameters)){
            $val = $parameters['apiKey'];
            unset($parameters['apiKey']);
            $this->setApiKey($val);
        }
        array_walk($parameters, [$this, 'updateQuery']);
        
    }
    
    /**
     * Attempts to apply default user information to parameters when none provided.
     *
     * @param array  $parameters
     *
     * @return void
     */
    protected function addDefaultUserInformationToParameters(&$parameters = [])
    {
        $defaultKeys = [
            'userip' => 'REMOTE_ADDR',
            'useragent' => 'HTTP_USER_AGENT',
            
        ];

        array_walk($defaultKeys, function ($value, $key) use (&$parameters) {
            if (!isset($parameters[$key]) && isset($_SERVER[$value])) {
                $parameters[$key] = $_SERVER[$value];
            }
        });
        if(!array_key_exists("action", $parameters)){
            $parameters['action']="jobs";
        }
        if(!array_key_exists("format", $parameters)){
            $parameters['format']=  $this->getFormat();
        }
        if(!array_key_exists("v", $parameters)){
            $parameters['v']=  '1.1';
        }
    }
    
    /**
     * Attempts to update current query parameters.
     *
     * @param  string  $value
     * @param  string  $key
     *
     * @return Indeed
     */
    protected function updateQuery($value, $key)
    {
        if (array_key_exists($key, $this->queryParams)) {
            $this->queryParams[$key] = $value;
        }

        return $this;
    }
    
    /**
     * Magic method to handle get and set methods for properties
     *
     * @param  string $method
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (isset($this->queryMap[$method], $parameters[0])) {
            $this->updateQuery($parameters[0], $this->queryMap[$method]);
        }

        return parent::__call($method, $parameters);
    }
    
    public function createJobObject($payload) {
    
        $payload = static::parseAttributeDefaults($payload, $this->jobDefaults);
//var_dump($payload);
        $job = $this->createJobFromPayload($payload);

        $job = $this->setJobLocation($job, $payload['location']);

        return $job->setCompany($payload['source'])
            ->setDatePostedAsString($payload['date']);
    }
    
    /**
     * Create new job from given payload
     *
     * @param  array $payload
     *
     * @return Job
     */
    protected function createJobFromPayload($payload = [])
    {
        return new Job([
            'title' => $payload['jobTitle'],
            'name' => $payload['jobTitle'],
            'description' => $payload['descriptionFragment'],
            'url' => $payload['jobViewUrl'],
            'sourceId' => $payload['jobListingId'],
            'location' => $payload['location'],
        ]);
    }

    public function getFormat() {
        $validFormats = ['json']; //support for XML is comming soon

        if (isset($this->queryParams['format'])
            && in_array(strtolower($this->queryParams['format']), $validFormats)) {
            return strtolower($this->queryParams['format']);
        }

        return 'json';
    }

    public function getListingsPath() {
        return 'response';
    }

    public function getUrl() {
        return 'http://api.glassdoor.com/api/api.htm?'.$this->getQueryString();
        
    }
    /**
     * Get query string for client based on properties
     *
     * @return string
     */
    public function getQueryString()
    {
        $location = $this->getLocation();

        if (!empty($location)) {
            $this->updateQuery($location, 'l');
        }

        return http_build_query($this->queryParams);
    }

    /**
     * Get combined location
     *
     * @return string
     */
    public function getLocation()
    {
        $locationArray = array_filter([$this->city, $this->state]);

        $location = implode(', ', $locationArray);

        if ($location) {
            return $location;
        }

        return null;
    }

    /**
     * Attempt to parse and add location to Job
     *
     * @param Job     $job
     * @param string  $location
     *
     * @return  Job
     */
    private function setJobLocation(Job $job, $location)
    {
        $location = static::parseLocation($location);

        if (isset($location[0])) {
            $job->setCity($location[0]);
        }
        if (isset($location[1])) {
            $job->setState($location[1]);
        }

        return $job;
    }

    public function getVerb() {
        return 'GET';
    }

    public function setPartnerId($val){
        $this->updateQuery($val, "t.p");
        
    }
    
    public function setApiKey($val){
        $this->updateQuery($val, "t.k");
    }

    /**
     * Create and get collection of jobs from given listings
     *
     * @param  array $listings
     *
     * @return Collection
     */
    protected function getJobsCollectionFromListings(array $listings = array())
    {
        $collection = new Collection;
//var_dump($listings['jobListings']);
        array_map(function ($item) use ($collection) {

            $job = $this->createJobObject($item);
            $job->setQuery($this->getKeyword())
                ->setSource($this->getSource());
            $collection->add($job);
        }, $listings['jobListings']);

        return $collection;
    }
}

