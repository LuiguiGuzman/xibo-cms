<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ShellCommandWidgetTestCase.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboShellCommand;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Tests\Integration\Widget\WidgetTestCase;

class ShellCommandWidgetTestCase extends WidgetTestCase
{
	protected $startLayouts;
    protected $startCommands;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining layouts and nuke them
        foreach ($finalLayouts as $layout) {
            /** @var XiboLayout $layout */
            $flag = true;
            foreach ($this->startLayouts as $startLayout) {
               if ($startLayout->layoutId == $layout->layoutId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $layout->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
        // tearDown all commands that weren't there initially
        $finalCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining commands and nuke them
        foreach ($finalCommands as $command) {
            /** @var XiboCommand $command */
            $flag = true;
            foreach ($this->startCommands as $startCom) {
               if ($startCom->commandId == $command->commandId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $command->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $command->commandId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * @group add
     * @dataProvider provideSuccessCases
     */
    public function testAdd($name, $duration, $windowsCommand, $linuxCommand, $launchThroughCmd, $terminateCommand, $useTaskkill, $commandCode)
    {
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunit code');
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('ShellCommand add Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);

        $response = $this->client->post('/playlist/widget/shellCommand/' . $region->playlists[0]['playlistId'], [
            'name' => $name,
            'duration' => $duration,
            'windowsCommand' => $windowsCommand,
            'linuxCommand' => $linuxCommand,
            'launchThroughCmd' => $launchThroughCmd,
            'terminateCommand' => $terminateCommand,
            'useTaskkill' => $useTaskkill,
            'commandCode' => $commandCode,
            ]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame($duration, $object->data->duration);        
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $windowsCommand, $linuxCommand, $launchThroughCmd, $terminateCommand, $useTaskkill, $commandCode)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Windows new command' => ['Api Windows command', 20, '-reboot', NULL, 1, null, 1, null],
            'Android new command' => ['Api Android command', 30, null, '-reboot', null, 1, null, null],
            'Previously created command' => ['Api shell command', 0, null, null, 1, 1, 1, 'phpunit code']
        ];
    }

     public function testEdit()
    {
        //parent::setupEnv();
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('ShellCommand edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a command with wrapper
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunit code');
        # Create a shell command widget with wrapper
        $shellCommand = (new XiboShellCommand($this->getEntityProvider()))->create('Api shell command', 0, null, null, 1, 1, 1, 'test code', $region->playlists[0]['playlistId']);
        $shellCommandCheck = (new XiboWidget($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $nameNew = 'Edited Name';
        $durationNew = 80;
        $commandCode = $command->code;
        $response = $this->client->put('/playlist/widget/' . $shellCommandCheck->widgetId, [
            'name' => $nameNew,
            'duration' => $durationNew,
            'windowsCommand' => null,
            'linuxCommand' => null,
            'launchThroughCmd' => 1,
            'terminateCommand' => 1,
            'useTaskkill' => 1,
            'commandCode' => $commandCode,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame($durationNew, $object->data->duration);
    }

    public function testDelete()
    {
        //parent::setupEnv();
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Shell Command delete Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a shell command widget with wrapper
        $shellCommand = (new XiboShellCommand($this->getEntityProvider()))->create('Api shell command', 0, null, null, 1, 1, 1, 'phpunit code', $region->playlists[0]['playlistId']);
        $shellCommandCheck = (new XiboWidget($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        # Delete it
        $this->client->delete('/playlist/widget/' . $shellCommandCheck->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
