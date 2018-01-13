<?php

namespace Freesewing\Data\Tests\Data;

use Freesewing\Data\Tests\TestApp;
use Freesewing\Data\Objects\User;
use Freesewing\Data\Objects\Model;
use Freesewing\Data\Objects\Referral;

class ReferralTest extends \PHPUnit\Framework\TestCase
{
    protected function setup() {
        if(!isset($this->app)) $this->app = new TestApp();
    }

    public function testCreate()
    {
        $obj = new Referral($this->app->getContainer());

        $host = 'test.freesewing.org';
        $path = '/foo/bar';
        $url = "https://$host/$path";

        $now = date('Y-m-d H:i');
        $id = $obj->create($host, $path, $url);
        $obj->load($id);
        $obj->setSite('Other');
        $obj->save($id);
        
        $this->assertEquals($obj->getHost(), $host);
        $this->assertEquals($obj->getPath(), $path);
        $this->assertEquals($obj->getUrl(), $url);
        $this->assertEquals($obj->getId(), $id);
        $this->assertEquals(substr($obj->getTime(), 0, 16), $now);
        $this->assertEquals($obj->getSite(), 'Other');
    }
    
    public function testGroup()
    {
        $obj = new Referral($this->app->getContainer());

        $host = 'test.freesewing.org';
        $path = '/foo/bar';
        $url = "https://$host/$path";

        $now = date('Y-m-d H:i');
        $id = $obj->create($host, $path, $url);
        $obj->load($id);
        $obj->group();
        
        $this->assertEquals($obj->getSite(), 'Freesewing');
    }
    
}