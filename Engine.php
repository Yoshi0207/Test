<?php

namespace Acms\Plugins\GoogleCalendar;

use DB;
use SQL;
use Field;

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
        $checkItems = array(
            'formId' => $this->config->get('calendar_submit_formid'),
            'time' => $this->config->get('calendar_submit_date'),
            'url' => $this->config->get('calendar_submit_url'),
            'ipAddr' => $this->config->get('calendar_submit_ip'),
            'ua' => $this->config->get('calendar_submit_agent'),
        );
        $values = array();

        foreach ($checkItems as $item => $check) {
            if ($check !== 'true') {
                continue;
            }
            $method = 'get' .ucwords($item);
            if (is_callable(array('self', $method))) {
                $values[] = call_user_func(array($this, $method));
            }
        }
        foreach ($field->_aryField as $key => $val) {
            $values[] = $this-> getCellData($field->get($key));
        }
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

        $event = new Google_Service_Calendar_Event(array(
            'summary' => 'テストの予定を登録するよ6', //予定のタイトル
            'start' => array(
                'dateTime' => '2019-06-01T10:00:00+09:00',// 開始日時
                'timeZone' => 'Asia/Tokyo',
            ),
            'end' => array(
                'dateTime' => '2019-06-01T11:00:00+09:00', // 終了日時
                'timeZone' => 'Asia/Tokyo',
            ),
        ));

        $response = $service->event->insert($calendarId, $event);

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
