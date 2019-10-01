<?php

class VerisignNameStudioResponse {

    private $raw;
    private $response;

    public function __construct($response) {
        $this->raw = $response;
        $this->response = $this->formatResponse($this->raw);
    }

    /**
     * Returns the response.
     *
     * @return stdClass A stdClass object representing the response, null if invalid response
     */
    public function response() {
        if ($this->response) {
          return $this->response;
        }
        return null;
    }

    /**
     * Get status of last request.
     *
     * @return boolean True if errors exist
     */
    public function ok() {
      return !(boolean) $this->error();
    }

    /**
     * Returns all errors contained in the response.
     *
     * @return stdClass A stdClass object representing the errors in the response, false if invalid response
     */
    public function error() {
        $response = $this->response();

        if (!is_object($response) && !empty($this->raw)) {
            return (object) [
                'code'    => 0000,
                'message' => $this->raw
            ];
        }

        if (!empty($response->code)) {
            return (object) [
                'code'  => $response->code,
                'message' => $response->message
            ];
        }

        return false;
    }

    /**
     * Returns the raw response.
     *
     * @return string The raw response
     */
    public function raw() {
        return $this->raw;
    }

    /**
     * Decodes the response.
     *
     * @param mixed $data The JSON data to convert to a stdClass object
     * @return stdClass $data in a stdClass object form
     */
    private function formatResponse($data) {
        $response = json_decode($data);

        return $response;
    }
}
