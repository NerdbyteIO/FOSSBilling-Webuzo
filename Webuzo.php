<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_Webuzo extends Server_Manager
{
    const enduserPort = 2003;

    /**
     * Get the server manager configuration form definition.
     *
     * @return array Form definition for the server manager configuration UI
     */
    public static function getForm(): array
    {
        return [
            "label" => "Webuzo",
            "form" => [
                "credentials" => [
                    "fields" => [
                        [
                            "name" => "username",
                            "type" => "text",
                            "label" => "Username",
                            "placeholder" =>
                                "Username to connect to the server",
                            "required" => true,
                        ],
                        [
                            "name" => "password",
                            "type" => "password",
                            "label" => "API Key",
                            "placeholder" => "API Key to connect to the server",
                            "required" => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the administrator port configured for Webuzo.
     *
     * Falls back to the default Webuzo administrator port when the configured
     * port is missing or invalid.
     *
     * @return int Administrator port number
     */
    public function getAdminPort(): int
    {
        $port = $this->_config["port"] ?? null;

        if (
            $port !== null &&
            filter_var($port, FILTER_VALIDATE_INT) !== false &&
            $port > 0 &&
            $port <= 65535
        ) {
            return (int) $port;
        }

        // Default Webuzo administrator port.
        return 2005;
    }

    /**
     * Get the account management login URL.
     *
     * Attempts to generate an SSO URL when an account is provided. If SSO fails,
     * the standard end-user panel URL is returned instead.
     *
     * @param Server_Account|null $account Account for which the login URL is generated
     *
     * @return string Login URL
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        if ($account) {
            try {
                $response = $this->SSORequest($account->getUsername());
                $url = $this->extractSsoUrl($response);

                $this->getLog()->info(
                    "SSO login successful for {$account->getUsername()}.",
                );

                return $url;
            } catch (Server_Exception $e) {
                $this->getLog()->warning(
                    "SSO login failed for {$account->getUsername()}, falling back to standard URL: " .
                        $e->getMessage(),
                );
            }
        }

        return sprintf(
            "https://%s:%s/",
            $this->_config["host"],
            self::enduserPort,
        );
    }

    /**
     * Get the reseller account management login URL.
     *
     * Attempts to generate an SSO URL when an account is provided. If SSO fails,
     * the standard administrator panel URL is returned instead.
     *
     * @param Server_Account|null $account Account for which the login URL is generated
     *
     * @return string Login URL
     */
    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        if ($account) {
            try {
                $response = $this->SSORequest($account->getUsername());

                return $this->extractSsoUrl($response);
            } catch (Server_Exception $e) {
                $this->getLog()->warning(
                    "SSO login failed for {$account->getUsername()}, falling back to standard URL: " .
                        $e->getMessage(),
                );
            }
        }

        return sprintf(
            "https://%s:%s/",
            $this->_config["host"],
            $this->getAdminPort(),
        );
    }

    /**
     * Test connectivity to the Webuzo API.
     *
     * @return bool True when the connection test succeeds
     */
    public function testConnection(): bool
    {
        $this->getLog()->info("Testing Webuzo API connection.");

        $this->request("adminRequest", "users");

        $this->getLog()->info("Connection to the Webuzo API was successful.");

        return true;
    }

    /**
     * Synchronize the local account object with the remote server state.
     *
     * @param Server_Account $account Account to synchronize
     *
     * @return Server_Account Synchronized account instance
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info(
            "Synchronizing account with server " . $account->getUsername(),
        );

        // Placeholder for future synchronization logic.
        return clone $account;
    }

    /**
     * Create a new account on the server.
     *
     * @param Server_Account $account Account to create
     *
     * @return bool True when the account is created successfully
     */
    public function createAccount(Server_Account $account): bool
    {
        $username = $account->getUsername();

        // Store the password once so it can be reused for both password fields.
        $password = $account->getPassword();

        $this->getLog()->info(
            "Creating account: {$username} using Webuzo server module.",
        );

        /**
         * Account provisioning is driven by plans defined in Webuzo itself.
         * As a result, reseller-specific branching is not required here.
         */
        $this->request("adminRequest", "add_user", [
            "create_user" => 1,
            "user" => $username,
            "user_passwd" => $password,
            "cnf_user_passwd" => $password,
            "domain" => $account->getDomain(),
            "email" => $account->getClient()->getEmail(),
            "plan" => strtolower($account->getPackage()->getName()),
            "billing_prefill" => 1,
        ]);

        $this->getLog()->info(
            "Account: {$username} created using Webuzo server module.",
        );

        return true;
    }

    /**
     * Suspend an account on the server.
     *
     * @param Server_Account $account Account to suspend
     *
     * @return bool True when the account is suspended successfully
     */
    public function suspendAccount(Server_Account $account): bool
    {
        /**
         * Reseller-specific handling is intentionally not applied here.
         * The expected behavior is to suspend the reseller account together
         * with any related child accounts.
         */
        $this->getLog()->info(
            "Suspending account {$account->getUsername()} using Webuzo server module.",
        );

        $this->request("adminRequest", "users", [
            "suspend" => $account->getUsername(),
        ]);

        return true;
    }

    /**
     * Unsuspend an account on the server.
     *
     * @param Server_Account $account Account to unsuspend
     *
     * @return bool True when the account is unsuspended successfully
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        $this->getLog()->info(
            "Unsuspending account {$account->getUsername()} using Webuzo server module.",
        );

        $this->request("adminRequest", "users", [
            "unsuspend" => $account->getUsername(),
        ]);

        return true;
    }

    /**
     * Cancel an account on the server.
     *
     * @param Server_Account $account Account to cancel
     *
     * @return bool True when the account is cancelled successfully
     */
    public function cancelAccount(Server_Account $account): bool
    {
        /**
         * When cancelling a reseller account, include the additional API
         * parameters required to remove both the reseller and all associated
         * subordinate accounts.
         */
        if ($account->getReseller()) {
            $this->request("adminRequest", "users", [
                "delete_user" => $account->getUsername(),
                "del_sub_acc" => 1,
                "skip_reseller" => 0,
            ]);

            return true;
        }

        $this->request("adminRequest", "users", [
            "delete_user" => $account->getUsername(),
        ]);

        return true;
    }

    /**
     * Change the package assigned to an account on the server.
     *
     * @param Server_Account $account Account for which the package should be changed
     * @param Server_Package $package New package to assign
     *
     * @return bool True when the package is changed successfully
     *
     * @throws Server_Exception This operation is not yet implemented
     */
    public function changeAccountPackage(
        Server_Account $account,
        Server_Package $package,
    ): bool {
        throw new Server_Exception(
            "Changing the Webuzo account package is not yet implemented.",
        );
    }

    /**
     * Change the username of an account on the server.
     *
     * @param Server_Account $account     Account for which the username should be changed
     * @param string         $newUsername New username
     *
     * @return bool True when the username is changed successfully
     *
     * @throws Server_Exception This operation is not yet implemented
     */
    public function changeAccountUsername(
        Server_Account $account,
        string $newUsername,
    ): bool {
        throw new Server_Exception(
            "Changing the Webuzo account username is not yet implemented.",
        );
    }

    /**
     * Change the primary domain of an account on the server.
     *
     * @param Server_Account $account   Account for which the domain should be changed
     * @param string         $newDomain New domain
     *
     * @return bool True when the domain is changed successfully
     *
     * @throws Server_Exception This operation is not yet implemented
     */
    public function changeAccountDomain(
        Server_Account $account,
        string $newDomain,
    ): bool {
        throw new Server_Exception(
            "Changing the Webuzo account domain is not yet implemented.",
        );
    }

    /**
     * Change the password of an account on the server.
     *
     * @param Server_Account $account     Account for which the password should be changed
     * @param string         $newPassword New password
     *
     * @return bool True when the password is changed successfully
     */
    public function changeAccountPassword(
        Server_Account $account,
        string $newPassword,
    ): bool {
        $this->request(
            "endUserRequest",
            "changepassword",
            [
                "changepass" => 1,
                "newpass" => $newPassword,
                "conf" => $newPassword,
            ],
            $account->getUsername(),
        );

        return true;
    }

    /**
     * Change the IP address assigned to an account on the server.
     *
     * @param Server_Account $account Account for which the IP should be changed
     * @param string         $newIp   New IP address
     *
     * @return bool True when the IP change is processed successfully
     *
     * @throws Server_Exception This operation is not yet implemented
     */
    public function changeAccountIp(
        Server_Account $account,
        string $newIp,
    ): bool {
        throw new Server_Exception(
            "Changing the Webuzo account IP address is not yet implemented.",
        );
    }

    /**
     * Send an API request to the remote Webuzo server.
     *
     * Builds and sends the HTTP request, validates the HTTP response,
     * decodes the JSON payload, and returns the decoded response.
     *
     * @param string      $requestType Request type identifier
     * @param string      $action      API action to execute
     * @param array       $params      Additional request parameters
     * @param string|null $loginAs     Username to impersonate for end-user requests
     *
     * @return mixed Decoded JSON response
     *
     * @throws Server_Exception When the request fails, the HTTP status is invalid,
     *                          the response is empty, the JSON is malformed, or
     *                          the API returns an error
     */
    public function request(
        string $requestType,
        string $action,
        array $params = [],
        ?string $loginAs = null,
    ): mixed {
        $params["apiuser"] = $this->_config["username"];
        $params["apikey"] = $this->_config["password"];

        if ($requestType === "endUserRequest") {
            if ($loginAs === null || $loginAs === "") {
                throw new Server_Exception(
                    "A loginAs value is required for end-user Webuzo requests.",
                );
            }

            $host = sprintf(
                "https://%s:%s/index.php?api=json&act=%s&loginAs=%s",
                $this->_config["host"],
                self::enduserPort,
                rawurlencode($action),
                rawurlencode($loginAs),
            );
        } elseif ($requestType === "adminRequest") {
            $host = sprintf(
                "https://%s:%s/index.php?api=json&act=%s",
                $this->_config["host"],
                $this->getAdminPort(),
                rawurlencode($action),
            );
        } else {
            throw new Server_Exception(
                "Unsupported Webuzo request type: :type",
                [":type" => $requestType],
            );
        }

        try {
            $client = $this->getHttpClient()->withOptions([
                "verify_peer" => $this->_config["verify_ssl"] ?? false,
                "verify_host" => $this->_config["verify_ssl"] ?? false,
                "timeout" => $this->_config["timeout"] ?? 30,
            ]);

            $response = $client->request("POST", $host, [
                "body" => $params,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Server_Exception(
                    "Webuzo returned HTTP :status for action :action",
                    [":status" => $statusCode, ":action" => $action],
                );
            }

            $result = $response->getContent();

            if ($result === "") {
                throw new Server_Exception(
                    "Webuzo returned an empty response for action :action",
                    [":action" => $action],
                );
            }

            $data = $this->decodeJsonResponse($result, $action);
            $error = $this->extractApiError($data);

            if ($error !== null) {
                throw new Server_Exception("Webuzo error (:action): :error", [
                    ":action" => $action,
                    ":error" => $error,
                ]);
            }

            return $data;
        } catch (Server_Exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new Server_Exception(
                "Failed to communicate with Webuzo (:action): :error",
                [":action" => $action, ":error" => $e->getMessage()],
            );
        }
    }

    /**
     * Request an SSO login URL for the specified account.
     *
     * @param string $loginAs Username to impersonate for SSO
     *
     * @return stdClass Decoded SSO response object
     *
     * @throws Server_Exception When communication with Webuzo fails or the response is invalid
     */
    public function SSORequest(string $loginAs): stdClass
    {
        $params = [
            "apiuser" => $this->_config["username"],
            "apikey" => $this->_config["password"],
        ];

        $host = sprintf(
            "https://%s:%s/index.php?loginAs=%s&api=json&act=sso&noip=1",
            $this->_config["host"],
            self::enduserPort,
            rawurlencode($loginAs),
        );

        try {
            $client = $this->getHttpClient()->withOptions([
                "verify_peer" => $this->_config["verify_ssl"] ?? false,
                "verify_host" => $this->_config["verify_ssl"] ?? false,
                "timeout" => $this->_config["timeout"] ?? 30,
            ]);

            $response = $client->request("POST", $host, [
                "body" => $params,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Server_Exception(
                    "Webuzo returned HTTP :status for action sso",
                    [":status" => $statusCode],
                );
            }

            $result = $response->getContent();

            if ($result === "") {
                throw new Server_Exception(
                    "Webuzo returned an empty response for action sso",
                );
            }

            $data = $this->decodeJsonResponse($result, "sso");
            $error = $this->extractApiError($data);

            if ($error !== null) {
                throw new Server_Exception("Webuzo error (sso): :error", [
                    ":error" => $error,
                ]);
            }

            if (!$data instanceof stdClass) {
                throw new Server_Exception(
                    "Invalid SSO response received from Webuzo.",
                );
            }

            return $data;
        } catch (Server_Exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new Server_Exception(
                "Failed to communicate with Webuzo (adminSSORequest): :error",
                [":error" => $e->getMessage()],
            );
        }
    }

    /**
     * Decode a JSON response body.
     *
     * @param string $result Response body
     * @param string $action API action name
     *
     * @return mixed Decoded JSON payload
     *
     * @throws Server_Exception When the response contains invalid JSON
     */
    private function decodeJsonResponse(string $result, string $action): mixed
    {
        $data = json_decode($result);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Server_Exception(
                "Invalid JSON from Webuzo (:action): :error",
                [
                    ":action" => $action,
                    ":error" => json_last_error_msg(),
                ],
            );
        }

        return $data;
    }

    /**
     * Extract an API error message from a decoded Webuzo response.
     *
     * @param mixed $data Decoded API response
     *
     * @return string|null Normalized error message, or null when no error exists
     */
    private function extractApiError(mixed $data): ?string
    {
        $errorPayload = null;

        if (is_object($data) && isset($data->error) && $data->error) {
            $errorPayload = $data->error;
        } elseif (is_array($data) && !empty($data["error"])) {
            $errorPayload = $data["error"];
        }

        if ($errorPayload === null) {
            return null;
        }

        if (is_array($errorPayload)) {
            return implode(
                "; ",
                array_map(function ($item): string {
                    if (is_scalar($item) || $item === null) {
                        return (string) $item;
                    }

                    return json_encode(
                        $item,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ) ?:
                        "Unknown error";
                }, $errorPayload),
            );
        }

        if (is_object($errorPayload)) {
            return json_encode(
                $errorPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) ?:
                "Unknown error";
        }

        return (string) $errorPayload;
    }

    /**
     * Extract and validate the SSO URL from a Webuzo response object.
     *
     * @param stdClass $response Decoded SSO response
     *
     * @return string Valid SSO URL
     *
     * @throws Server_Exception When the SSO URL is missing or invalid
     */
    private function extractSsoUrl(stdClass $response): string
    {
        $url = $response->done->url ?? null;

        if (!is_string($url) || $url === "") {
            throw new Server_Exception(
                "Webuzo did not return a valid SSO URL.",
            );
        }

        return $url;
    }
}
