<?php

class Time_Slot_Generator {
    /** Character supported in source **/
    const SUPPORTED_CHARS = 'a-zA-Z0-9:-';

    protected $daysOfWeek = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Current position in source **/
    protected $pos = 0;

    /** Source **/
    protected $src;

    protected $timeSlotInfo = [
        'interval' => null, /** generation interval in minutes **/
        'from' => null, /** starting date **/
        'to' => null, /** ending date **/
        'hours' => [
            'mon' => ['ranges' => [], 'at' => []], /** monday working hours **/
            'tue' => ['ranges' => [], 'at' => []], /** tuesday working hours **/
            'wed' => ['ranges' => [], 'at' => []], /** wednesday working hours **/
            'thu' => ['ranges' => [], 'at' => []], /** thursday working hours **/
            'fri' => ['ranges' => [], 'at' => []], /** friday working hours **/
            'sat' => ['ranges' => [], 'at' => []], /** saturday working hours **/
            'sun' => ['ranges' => [], 'at' => []] /** sunday working hours **/
        ]
    ];

    /** Current parser state **/
    protected $currState;

    /** Supported states **/
    const STATE_INTERVAL = 1;
    const STATE_FROM     = 2;
    const STATE_TO       = 3;
    const STATE_DAY      = 4;
    const STATE_END      = 5;

    /** Last read token **/
    protected $token;

    protected function validateEnd()
    {
        if ($this->pos >= strlen($this->src)) {
            throw new Exception('Unexpected end of file', self::STATE_END);
        }
    }

    protected function isSpace($str) : bool
    {
        return !preg_match('#[' . self::SUPPORTED_CHARS . ']#', $str);
	}

    /** Read and store token **/
    protected function readToken($checkEof = true) : void
    {
        $this->token = null;

        $this->skipSpaces();

        while ($this->pos < strlen($this->src)
            && !$this->isSpace($this->src[$this->pos]))
        {
            $this->token .= strtolower($this->src[$this->pos]);
            $this->pos++;
        }

        if ($checkEof) {
            $this->validateEnd();
        }
    }

    /** Return last token **/
    protected function returnToken() : void
    {
        $this->pos -= strlen($this->token);
    }

    protected function skipSpaces() : void
    {
        while($this->pos < strlen($this->src)
              && $this->isSpace($this->src[$this->pos]))
        {
            $this->pos++;
        }
    }

    protected function parseInterval() : int
    {
        $this->readToken();
        if ($this->token != 'every') {
            throw new Exception(
                sprintf('Syntax error. State: %s', $this->currState)
            );
        }

        $this->readToken();
        if (!is_numeric($this->token)) {
            throw new Exception(
                sprintf('Syntax error. Inverval value must be an integer. State: %s', $this->currState)
            );
        }

        $this->timeSlotInfo['interval'] = (int) $this->token;

        $this->readToken();
        if ($this->token != 'minutes') {
            throw new Exception(
                sprintf('Syntax error. State: %s', $this->currState)
            );
        }

        return self::STATE_FROM;
    }

    protected function parseFrom() : int
    {
        $this->readToken();
        if ($this->token != 'from') {
            throw new Exception(
                sprintf('Syntax error. State: %s', $this->currState)
            );
        }

        $this->readToken();
        if (strtotime($this->token . ' 00:00:00') == false) {
            throw new Exception(
                sprintf('Syntax error. Invalid from date. State: %s', $this->currState)
            );
        }

        $this->timeSlotInfo['from'] = strtotime($this->token . ' 00:00:00');

        return self::STATE_DAY;
    }

    protected function parseTo() : int
    {
        $this->readToken();
        if ($this->token != 'to') {
            throw new Exception(
                sprintf('Syntax error. State: %s', $this->currState)
            );
        }

        $this->readToken(false);
        if (strtotime($this->token . ' 23:59:00') == false) {
            throw new Exception(
                sprintf('Syntax error. Invalid to date. State: %s', $this->currState)
            );
        }

        $this->timeSlotInfo['to'] = strtotime($this->token . ' 23:59:00');

        return self::STATE_END;
    }

    protected function parseHours($day) : void
    {
        $this->readToken();
        if ($this->token == 'within') {
            $this->readToken();
            if (!preg_match('#[0-9]{2}:[0-9]{2}-[0-9]{2}:[0-9]{2}#', $this->token)) {
                throw new Exception(
                    sprintf(
                        'Syntax error. Invalid working hours range format. State: %s', $this->currState
                    )

                );
            }
            list($timeFrom, $timeTo) = explode('-', $this->token);
            $this->timeSlotInfo['hours'][$day]['ranges'][] = [
                'from' => $this->timeToSeconds($timeFrom),
                'to' => $this->timeToSeconds($timeTo)
            ];
        } else if ($this->token == 'at') {
            $this->readToken();
            if (!preg_match('#[0-9]{2}:[0-9]{2}#', $this->token)) {
                throw new Exception(
                    sprintf(
                        'Syntax error. Invalid working hours fixed time format. State: %s', $this->currState
                    )
                );
            }
            $this->timeSlotInfo['hours'][$day]['at'][] = $this->timeToSeconds($this->token);
        } else {
            throw new Exception(sprintf('Syntax error. State: %s', $this->currState));
        }
    }

    protected function parseDay() : int
    {
        $this->readToken();
        if (!in_array($this->token, $this->daysOfWeek)) {
            throw new Exception(
                sprintf('Syntax error. Invalid name of week day. State: %s', $this->currState)
            );
        }
        $day = $this->token;

        $this->parseHours($day);
        $this->readToken();

        while($this->token == 'and') {
            $this->parseHours($day);
            $this->readToken();
        }

        if (in_array($this->token, $this->daysOfWeek)) {
            $this->returnToken();
            return self::STATE_DAY;
        } else if ($this->token == 'to') {
            $this->returnToken();
            return self::STATE_TO;
        }

        throw new Exception(
            sprintf('Unexpected token. State: %s', $this->currState)
        );
    }

    protected function timeToSeconds($time) : int
    {
        list($hours, $minutes) = explode(':', $time);
        return ((int)$hours * 60 * 60) + ((int)$minutes * 60);
    }

    public function parse(string $rule) : void
    {
        $this->src = trim($rule);
        while ($this->currState != self::STATE_END) {
            if (is_null($this->currState)) {
                $this->currState = self::STATE_INTERVAL;
            }
            try {
                switch ($this->currState) {
                    case self::STATE_INTERVAL:
                        $this->currState = $this->parseInterval();
                        break;
                    case self::STATE_FROM:
                        $this->currState = $this->parseFrom();
                        break;
                    case self::STATE_DAY:
                        $this->currState = $this->parseDay();
                        break;
                    case self::STATE_TO:
                        $this->currState = $this->parseTo();
                        break;
                    default:
                        throw new Exception('Unexpected state');
                        break;
                }
            } catch (Exception $e) {
                if ($e->getCode() == self::STATE_END) {
                    $this->currState = self::STATE_END;
                } else {
                    throw $e;
                }
            }
        }
    }

    public function generateTimeSlots() : array
    {
        $timeSlots = [];

        $currentTimestamp = $this->timeSlotInfo['from'];
        do {
            $currDay = strtolower(date('D', $currentTimestamp));
            $seconds = $this->timeToSeconds(date('H:i', $currentTimestamp));
            foreach ($this->timeSlotInfo['hours'][$currDay]['at'] as $fixed) {
                if ($seconds == $fixed) {
                    $timeSlots[] = date('Y-m-d H:i:s', $currentTimestamp);
                }
            }
            foreach ($this->timeSlotInfo['hours'][$currDay]['ranges'] as $range) {
                if ($seconds == $range['from'] || $seconds == $range['to']) {
                    $timeSlots[] = date('Y-m-d H:i:s', $currentTimestamp);
                } else if ($seconds > $range['from'] && $seconds < $range['to']) {
                    if ((($seconds - $range['from']) / 60) % $this->timeSlotInfo['interval'] == 0) {
                        $timeSlots[] = date('Y-m-d H:i:s', $currentTimestamp);
                    }
                }
            }
            $currentTimestamp += 60; // 1 minute step
        } while ($currentTimestamp <= $this->timeSlotInfo['to']);

        return $timeSlots;
    }
}

