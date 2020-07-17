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

        // 各設定項目について、チェックボックスの真偽値を取得
        // ablog cms カスタムフィールドを使用：true
        // ablog cms カスタムフィールドを使用しない：false
        // $checkItems:bool[]
        $checkItems = array(
            'calendar_event_title' => $this->config->get('calendar_event_title_check'),
            'calendar_event_location' => $this->config->get('calendar_event_location_check'),
            'calendar_event_description' => $this->config->get('calendar_event_description_check'),
            'calendar_start_date' => $this->config->get('calendar_start_date_check'),
            'calendar_start_time' => $this->config->get('calendar_start_time_check'),
            'calendar_end_date' => $this->config->get('calendar_end_date_check'),
            'calendar_end_time' => $this->config->get('calendar_end_time_check'),
            'calendar_event_timeZone' => $this->config->get('calendar_event_timeZone_check'),
        );

        // 各設定項目について、記述されている値を取得
        // $formField:string[]
        $formItems = array(
            'calendar_event_title' => $this->config->get('calendar_event_title'),
            'calendar_event_location' => $this->config->get('calendar_event_location'),
            'calendar_event_description' => $this->config->get('calendar_event_description'),
            'calendar_start_date' => $this->config->get('calendar_start_date'),
            'calendar_start_time' => $this->config->get('calendar_start_time'),
            'calendar_end_date' => $this->config->get('calendar_end_date'),
            'calendar_end_time' => $this->config->get('calendar_end_time'),
            'calendar_event_timeZone' => $this->config->get('calendar_event_timeZone'),
        );

        $values = array(
            // 予定タイトル
            'summary' => $checkItems["calendar_event_title"] ? $field->get($formItems["calendar_event_title"]) : $formItems["calendar_event_title"],
            'location' => $checkItems["calendar_event_location"] ? $field->get($formItems["calendar_event_location"]) : $formItems["calendar_event_location"],
            'description' => $checkItems["calendar_event_description"] ? $field->get($formItems["calendar_event_description"]) : $formItems["calendar_event_description"],

            // 開始時刻 yy-mm-ddT00:00:00timezone
            'start' => array(
                'dateTime' => $field->get($formItems["calendar_start_date"])."T".$field->get($formItems["calendar_start_time"]),// 開始日時
                'timeZone' => $formItems["calendar_event_timeZone"],
            ),

            // 終了時刻
            'end' => array(
                'dateTime' => $field->get($formItems["calendar_end_date"])."T".$field->get($formItems["calendar_end_time"]), // 終了日時
                'timeZone' => $formItems["calendar_event_timeZone"],
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
