<?php

namespace Guzzle\Tests\Service\Command\LocationVisitor\Response;

use Guzzle\Service\Description\Parameter;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Command\LocationVisitor\Response\XmlVisitor as Visitor;

/**
 * @covers Guzzle\Service\Command\LocationVisitor\Response\XmlVisitor
 */
class XmlVisitorTest extends AbstractResponseVisitorTest
{
    public function testBeforeMethodParsesXml()
    {
        $visitor = new Visitor();
        $command = $this->getMockBuilder('Guzzle\Service\Command\AbstractCommand')
            ->setMethods(array('getResponse'))
            ->getMockForAbstractClass();
        $command->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue(new Response(200, null, '<foo><Bar>test</Bar></foo>')));
        $result = array();
        $visitor->before($command, $result);
        $this->assertEquals(array('Bar' => 'test'), $result);
    }

    public function testBeforeMethodParsesNestedXml()
    {
        $visitor = new Visitor();
        $command = $this->getMockBuilder('Guzzle\Service\Command\AbstractCommand')
            ->setMethods(array('getResponse'))
            ->getMockForAbstractClass();
        $command->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue(new Response(200, null, '<foo><Items><Bar>test</Bar></Items></foo>')));
        $result = array();
        $visitor->before($command, $result);
        $this->assertEquals(array('Items' => array('Bar' => 'test')), $result);
    }

    public function testCanExtractAndRenameTopLevelXmlValues()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'location' => 'xml',
            'name'     => 'foo',
            'sentAs'   => 'Bar'
        ));
        $value = array('Bar' => 'test');
        $visitor->visit($this->command, $this->response, $param, $value);
        $this->assertArrayHasKey('foo', $value);
        $this->assertEquals('test', $value['foo']);
    }

    public function testEnsuresRepeatedArraysAreInCorrectLocations()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'location' => 'xml',
            'name'     => 'foo',
            'sentAs'   => 'Foo',
            'type'     => 'array',
            'items'    => array(
                'type' => 'object',
                'properties' => array(
                    'Bar' => array('type' => 'string'),
                    'Baz' => array('type' => 'string'),
                    'Bam' => array('type' => 'string')
                )
            )
        ));

        $xml = new \SimpleXMLElement('<Test><Foo><Bar>1</Bar><Baz>2</Baz></Foo></Test>');
        $value = json_decode(json_encode($xml), true);
        $visitor->visit($this->command, $this->response, $param, $value);
        $this->assertEquals(array(
            'foo' => array(
                array (
                    'Bar' => '1',
                    'Baz' => '2'
                )
            )
        ), $value);
    }

    public function testEnsuresFlatArraysAreFlat()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'location' => 'xml',
            'name'     => 'foo',
            'type'     => 'array',
            'items'    => array('type' => 'string')
        ));

        $value = array('foo' => array('bar', 'baz'));
        $visitor->visit($this->command, $this->response, $param, $value);
        $this->assertEquals(array('foo' => array('bar', 'baz')), $value);

        $value = array('foo' => 'bar');
        $visitor->visit($this->command, $this->response, $param, $value);
        $this->assertEquals(array('foo' => array('bar')), $value);
    }

    public function xmlDataProvider()
    {
        $param = new Parameter(array(
            'location' => 'xml',
            'name'     => 'Items',
            'type'     => 'array',
            'items'    => array(
                'type' => 'object',
                'name' => 'Item',
                'properties' => array(
                    'Bar' => array('type' => 'string'),
                    'Baz' => array('type' => 'string')
                )
            )
        ));

        return array(
            array($param, '<Test><Items><Item><Bar>1</Bar></Item><Item><Bar>2</Bar></Item></Items></Test>', array(
                'Items' => array(
                    array('Bar' => 1),
                    array('Bar' => 2)
                )
            )),
            array($param, '<Test><Items><Item><Bar>1</Bar></Item></Items></Test>', array(
                'Items' => array(
                    array('Bar' => 1)
                )
            )),
            array($param, '<Test><Items /></Test>', array(
                'Items' => array()
            ))
        );
    }

    /**
     * @dataProvider xmlDataProvider
     */
    public function testEnsuresWrappedArraysAreInCorrectLocations($param, $xml, $result)
    {
        $visitor = new Visitor();
        $xml = new \SimpleXMLElement($xml);
        $value = json_decode(json_encode($xml), true);
        $visitor->visit($this->command, $this->response, $param, $value);
        $this->assertEquals($result, $value);
    }

    public function testCanRenameValues()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'name'     => 'TerminatingInstances',
            'type'     => 'array',
            'location' => 'xml',
            'sentAs'   => 'instancesSet',
            'items'    => array(
                'name' => 'item',
                'type' => 'object',
                'sentAs' => 'item',
                'properties' => array(
                    'InstanceId' => array(
                        'type'   => 'string',
                        'sentAs' => 'instanceId',
                    ),
                    'CurrentState' => array(
                        'type'   => 'object',
                        'sentAs' => 'currentState',
                        'properties' => array(
                            'Code' => array(
                                'type' => 'numeric',
                                'sentAs' => 'code',
                            ),
                            'Name' => array(
                                'type' => 'string',
                                'sentAs' => 'name',
                            ),
                        ),
                    ),
                    'PreviousState' => array(
                        'type'   => 'object',
                        'sentAs' => 'previousState',
                        'properties' => array(
                            'Code' => array(
                                'type' => 'numeric',
                                'sentAs' => 'code',
                            ),
                            'Name' => array(
                                'type' => 'string',
                                'sentAs' => 'name',
                            ),
                        ),
                    ),
                ),
            )
        ));

        $value = array(
            'instancesSet' => array (
                'item' => array (
                    'instanceId' => 'i-3ea74257',
                    'currentState' => array(
                        'code' => '32',
                        'name' => 'shutting-down',
                    ),
                    'previousState' => array(
                        'code' => '16',
                        'name' => 'running',
                    ),
                ),
            )
        );

        $visitor->visit($this->command, $this->response, $param, $value);

        $this->assertEquals(array(
            'TerminatingInstances' => array(
                array(
                    'InstanceId' => 'i-3ea74257',
                    'CurrentState' => array(
                        'Code' => '32',
                        'Name' => 'shutting-down',
                    ),
                    'PreviousState' => array(
                        'Code' => '16',
                        'Name' => 'running',
                    )
                )
            )
        ), $value);
    }

    public function testCanRenameAttributes()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'name'     => 'RunningQueues',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'type' => 'object',
                'sentAs' => 'item',
                'properties' => array(
                    'QueueId' => array(
                        'type'   => 'string',
                        'sentAs' => 'queue_id',
                        'data'   => array(
                            'xmlAttribute' => true,
                        ),
                    ),
                    'CurrentState' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'Code' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'code',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                            'Name' => array(
                                'sentAs' => 'name',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                        ),
                    ),
                    'PreviousState' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'Code' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'code',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                            'Name' => array(
                                'sentAs' => 'name',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                        ),
                    ),
                ),
            )
        ));

        $xml = '<wrap><RunningQueues><item queue_id="q-3ea74257"><CurrentState code="32" name="processing" /><PreviousState code="16" name="wait" /></item></RunningQueues></wrap>';
        $value = json_decode(json_encode(new \SimpleXMLElement($xml)), true);
        $visitor->visit($this->command, $this->response, $param, $value);

        $this->assertEquals(array(
            'RunningQueues' => array(
                array(
                    'QueueId' => 'q-3ea74257',
                    'CurrentState' => array(
                        'Code' => '32',
                        'Name' => 'processing',
                    ),
                    'PreviousState' => array(
                        'Code' => '16',
                        'Name' => 'wait',
                    ),
                ),
            )
        ), $value);
    }

    public function testAddsEmptyArraysWhenValueIsMissing()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'name'     => 'Foo',
            'type'     => 'array',
            'location' => 'xml',
            'items' => array(
                'type' => 'object',
                'properties' => array(
                    'Baz' => array('type' => 'array'),
                    'Bar' => array(
                        'type'   => 'object',
                        'properties' => array(
                            'Baz' => array('type' => 'array'),
                         )
                    )
                )
            )
        ));

        $value = array();
        $visitor->visit($this->command, $this->response, $param, $value);

        $value = array(
            'Foo' => array(
                'Bar' => array()
            )
        );
        $visitor->visit($this->command, $this->response, $param, $value);
        $this->assertEquals(array(
            'Foo' => array(
                array(
                    'Bar' => array()
                )
            )
        ), $value);
    }

    public function testEnsuresArraysDropAttributes()
    {
        $visitor = new Visitor();
        $param = new Parameter(array(
            'name' => 'Items',
            'type' => 'array',
            'location' => 'xml',
            'items' => array(
                'sentAs' => 'Item',
                'type' => 'object'
            )
        ));

        $xml = '<wrap><Items size="1"><Item /></Items><Bar></Bar></wrap>';
        $value = json_decode(json_encode(new \SimpleXMLElement($xml)), true);
        $visitor->visit($this->command, $this->response, $param, $value);

        $this->assertEquals(array(
            'Bar' => array(),
            'Items' => array(
                array()
            ),
        ), $value);
    }
}
