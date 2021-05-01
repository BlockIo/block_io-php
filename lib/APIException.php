<?php

namespace BlockIo;

class APIException extends \Exception
{
    public $raw_data;

    public function setRawData($data) {
        $this->raw_data = $data;
    }

    public function getRawData() {
        return $this->raw_data;
    }
}

?>
