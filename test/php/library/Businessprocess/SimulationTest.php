<?php

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class SimulationTest extends BaseTestCase
{
    public function testSimulationInstantiation()
    {
        $class = 'Icinga\\Module\\Businessprocess\\Simulation';
        $this->assertInstanceOf(
            $class,
            Simulation::create()
        );
    }

    public function testAppliedSimulation()
    {
        $data = (object) array(
            'state'        => 0,
            'acknowledged' => false,
            'in_downtime'  => false
        );
        $config = $this->makeInstance()->loadProcess('simple_with-header');
        $simulation = Simulation::create(array(
            'host1;Hoststatus' => $data
        ));
        $parent = $config->getBpNode('singleHost');

        $config->applySimulation($simulation);
        $this->assertEquals(
            'OK',
            $parent->getStateName()
        );

        $parent->clearState();
        $data->state = 1;
        $simulation->set('host1;Hoststatus', $data);
        $config->applySimulation($simulation);
        $this->assertEquals(
            'CRITICAL',
            $parent->getStateName()
        );
    }
}
