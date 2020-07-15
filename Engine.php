<?php

namespace Acms\Plugins\GoogleCalendar;

use DB;
use SQL;
use Field;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class Engine
{
    /**
     * @var \Field
     */
    protected $formField;

    /**
     * @var \Field
     */
    protected $config;

    /**
     * Engine constructor.
     * @param string $code
     */
    public function __construct($code, $module)
    {
        $field = $this->loadFrom($code);
        if (empty($field)) {
            throw new \RuntimeException('Not Found Form.');
        }
        $this->formField = $field;
        $this->module = $module;
        $this->code = $code;
        $this->config = $field->getChild('mail');
    }

    /**
     * Update Google Calendar
     */
    public function send()
    {
        $field = $this->module->Post->getChild('field');
        $Items = array(
            'event_title' => $this->config->get('calendar_event_title'),
            'start_date' => $this->config->get('calendar_start_date'),
            'start_time' => $this->config->get('calendar_start_time'),
            'end_date' => $this->config->get('calendar_end_date'),
            'end_time' => $this->config->get('calendar_end_time'),
        );
        /*
        $values = array(
            'summary' => '{$this->config->get($Items[event_title])}', //予定のタイトル
            'start' => array(
                'dateTime' => '{$this->config->get($Items[start_date])}T10:00:00+09:00',// 開始日時
                'timeZone' => 'Asia/Tokyo',
            ),
            'end' => array(
                'dateTime' => '2020-07-20T11:00:00+09:00', // 終了日時
                'timeZone' => 'Asia/Tokyo',
            ),
        );*/
        echo $Items;
        echo $this->config->get('calendar_event_title');
        echo $this->config->get('calendar_start_date');
        echo $this->config->get('calendar_start_time');
        echo $this->config->get('calendar_end_date');
        echo $this->config->get('calendar_end_time');
        $values = array(
            'summary' => "サンプル", //予定のタイトル
            'start' => array(
                'dateTime' => '2020-07-20T10:00:00+09:00',// 開始日時
                'timeZone' => 'Asia/Tokyo',
            ),
            'end' => array(
                'dateTime' => '2020-07-20T11:00:00+09:00', // 終了日時
                'timeZone' => 'Asia/Tokyo',
            ),
        );

        $this->update($values);
    }

    /**
     * Send Google Calendar Api
     *
     * @param array $values
     */
    protected function update($values)
    {
        $client = (new Api())->getClient();
        if (!$client->getAccessToken()) {
            throw new \RuntimeException('Failed to get the access token.');
        }
        $service = new Google_Service_Calendar($client);
        $calendarId = $this->config->get('calendar_id');

        $event = new Google_Service_Calendar_Event($values);

        $response = $service->events->insert($calendarId, $event);

        if (!$response->valid()) {
            throw new \RuntimeException('Failed to update the calendar.');
        }
    }


    /**
     * @param string $code
     * @return bool|Field
     */
    protected function loadFrom($code)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('form');
        $SQL->addWhereOpr('form_code', $code);
        $row = $DB->query($SQL->get(dsn()), 'row');

        if (!$row) {
            return false;
        }
        $Form = new Field();
        $Form->set('code', $row['form_code']);
        $Form->set('name', $row['form_name']);
        $Form->set('scope', $row['form_scope']);
        $Form->set('log', $row['form_log']);
        $Form->overload(unserialize($row['form_data']), true);

        return $Form;
    }

    /**
     * @param string $value
     * @return \Google_Service_Calendar_CellData
     */
    private function getCellData($value)
    {
        $cellData = new Google_Service_Calendar_CellData();
        $extendedValue = new Google_Service_Calendar_ExtendedValue();
        $extendedValue->setStringValue($value);
        $cellData->setUserEnteredValue($extendedValue);
        return $cellData;
    }

    /**
     * @return \Google_Service_Calendar_CellData
     */
    private function getTime()
    {
        return $this->getCellData(date('Y-m-d H:i:s', REQUEST_TIME));
    }

    /**
     * @return \Google_Service_Calendar_CellData
     */
    private function getFormId()
    {
        return $this->getCellData($this->code);
    }

    /**
     * @return \Google_Service_Calendar_CellData
     */
    private function getUrl()
    {
        return $this->getCellData(REQUEST_URL);
    }

    /**
     * @return \Google_Service_Calendar_CellData
     */
    private function getIpAddr()
    {
        return $this->getCellData(REMOTE_ADDR);
    }

    /**
     * @return \Google_Service_Calendar_CellData
     */
    private function getUa()
    {
        return $this->getCellData(UA);
    }
}
