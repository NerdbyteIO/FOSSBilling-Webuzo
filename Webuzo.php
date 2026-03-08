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
    /**
     * Returns server manager parameters.
     *
     * @return array returns an array with the label of the server manager
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
                            "type" => "text",
                            "label" => "API Key",
                            "placeholder" => "API Key to connect to the server",
                            "required" => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getPort(): int
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
        // Default port for Webuzo
        return 2005;
    }

    /**
     * Returns the URL for account management.
     *
     * @param Server_Account|null $account the account for which the URL is generated
     *
     * @return string returns the URL as a string
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        return "https://google.com";
    }

    /**
     * Returns the URL for reseller account management.
     *
     * @param Server_Account|null $account the account for which the URL is generated
     *
     * @return string returns the URL as a string
     */
    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return "https://google.com";
    }

    /**
     * Tests the connection to the server.
     *
     * @return bool returns true if the connection is successful
     */
    public function testConnection(): bool
    {
        $this->getLog()->info("Testing Webuzo API Connection");

        $data = $this->adminAPIRequest("users");

        $this->getLog()->info("Connection to Webuzo API was successful!");

        $this->getLog()->info(serialize($data));

        return true;
    }

    /**
     * Synchronizes the account with the server.
     *
     * @param Server_Account $account the account to be synchronized
     *
     * @return Server_Account returns the synchronized account
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info(
            "Synchronizing account with server " . $account->getUsername(),
        );

        // @example - retrieve username from server and set it to cloned object
        // $new->setUsername('newusername');
        return clone $account;
    }

    /**
     * Creates a new account on the server.
     *
     * @param Server_Account $account the account to be created
     *
     * @return bool returns true if the account is successfully created
     */
    public function createAccount(Server_Account $account): bool
    {
        $username = $account->getUsername();

        // We simply make this a variable
        // so we aren't calling it
        // twice as we have to
        // confirm the pass.
        $password = $account->getPassword();

        $this->getLog()->info(
            "Creating account: {$username} using Webuzo server module.",
        );

        /**
         * As we will be forcing the user that is using
         * this server module to use the plans in
         * the webuzo CP.  We don't need to
         * worry about if the account is
         * a reseller here.
         */

        $this->adminAPIRequest("add_user", [
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
            "Account: {$username} created, using Webuzo server module.",
        );

        return true;
    }

    /**
     * Suspends an account on the server.
     *
     * @param Server_Account $account the account to be suspended
     *
     * @return bool returns true if the account is successfully suspended
     */
    public function suspendAccount(Server_Account $account): bool
    {
        // We simply don't care if they are a reseller at
        // this point as we will never skip=1, as
        // the reseller user and its clients
        // should be suspended, I can't
        // think of a case where I
        // would want just its
        // clients suspended
        // and not the
        // reseller.

        $this->getLog()->info(
            "Suspending account, " .
                $account->getUsername() .
                " using Webuzo server module.",
        );

        $this->adminAPIRequest("users", ["suspend" => $account->getUsername()]);

        return true;
    }

    /**
     * Unsuspends an account on the server.
     *
     * @param Server_Account $account the account to be unsuspended
     *
     * @return bool returns true if the account is successfully unsuspended
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        $this->getLog()->info(
            "Unsuspending account, " .
                $account->getUsername() .
                " using Webuzo server module.",
        );
        $this->adminAPIRequest("users", [
            "unsuspend" => $account->getUsername(),
        ]);

        return true;
    }

    /**
     * Cancels an account on the server.
     *
     * @param Server_Account $account the account to be cancelled
     *
     * @return bool returns true if the account is successfully cancelled
     */
    public function cancelAccount(Server_Account $account): bool
    {
        // Check if the account is reseller if it is
        // we add 2 new params to the api request
        // this is to delete the reseller & sub
        // accounts.  We then return early
        // to not continue processing
        // the shared hosting
        // account deleation.
        if ($account->getReseller()) {
            $this->adminAPIRequest("users", [
                "delete_user" => $account->getUsername(),
                "del_sub_acc" => 1, // We want to go through and delete all the sub user accounts under this reseller.
                "skip_reseller" => 0, // We also ensure that we go ahead and delete the reseller account.
            ]);

            return true;
        }

        $this->adminAPIRequest("users", [
            "delete_user" => $account->getUsername(),
        ]);

        return true;
    }

    /**
     * Changes the package of an account on the server.
     *
     * @param Server_Account $account the account for which the package is to be changed
     * @param Server_Package $package the new package
     *
     * @return bool returns true if the package is successfully changed
     */
    public function changeAccountPackage(
        Server_Account $account,
        Server_Package $package,
    ): bool {
        /**
        * Needs further investigation as to what needs to happen here.
        */
    }

    /**
     * Changes the username of an account on the server.
     *
     * @param Server_Account $account     the account for which the username is to be changed
     * @param string         $newUsername the new username
     *
     * @return bool returns true if the username is successfully changed
     */
    public function changeAccountUsername(
        Server_Account $account,
        string $newUsername,
    ): bool {
        /**
        * Not 100% sure this is something that is supported, as 
        * AdminAPIRequest expects that you go to action
        * add_user and pass in edit user, but it 
        * always wants a password change.
        */
    }

    /**
     * Changes the domain of an account on the server.
     *
     * @param Server_Account $account   the account for which the domain is to be changed
     * @param string         $newDomain the new domain
     *
     * @return bool returns true if the domain is successfully changed
     */
    public function changeAccountDomain(
        Server_Account $account,
        string $newDomain,
    ): bool {
        /**
        * Looks like this function may be possiable looking over the API docs
        * Planned to get working.
        */
    }

    /**
     * Changes the password of an account on the server.
     *
     * @param Server_Account $account     the account for which the password is to be changed
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password is successfully changed
     */
    public function changeAccountPassword(
        Server_Account $account,
        string $newPassword,
    ): bool {
        /**
         * This is a function that requires the endUserRequestAPI
         * Reviewing the API docs to see what all needs to be
         * done for this to start working.
         */

        return true;
    }

    /**
     * Changes the IP of an account on the server.
     *
     * @param Server_Account $account the account for which the IP is to be changed
     * @param string         $newIp   the new IP
     *
     * @return bool returns true if the IP is successfully changed
     */
    public function changeAccountIp(
        Server_Account $account,
        string $newIp,
    ): bool {
        if ($account->getReseller()) {
            $this->getLog()->info("Changing reseller hosting account ip");
        } else {
            $this->getLog()->info("Changing shared hosting account ip");
        }

        return true;
    }

    /**
     * Sends an API request to the remote server module.
     *
     * This method constructs and sends a POST request to the configured server,
     * handles the response, and returns the decoded JSON data.
     *
     * @param string action The API action to perform (e.g., 'suspend', 'unsuspend', 'create')
     * @param array $params Optional parameters to send with the request
     *
     * @return mixed The decoded JSON response from the server (as object)
     *
     * @throws Server_Exception If the HTTP request fails, returns invalid status, or JSON is malformed
     */
    public function adminAPIRequest(string $action, array $params = []): mixed
    {
        $params["apiuser"] = $this->_config["username"];
        $params["apikey"] = $this->_config["password"];

        $host = sprintf(
            "https://%s:%s/index.php?api=json&act=%s",
            $this->_config["host"],
            $this->getPort(),
            $action,
        );

        try {
            $client = $this->getHttpClient()->withOptions([
                "verify_peer" => $this->_config["verify_ssl"] ?? false,
                "verify_host" => $this->_config["verify_ssl"] ?? false,
                "timeout" => $this->_config["timeout"] ?? 30,
            ]);

            $response = $client->request("POST", $host, [
                "headers" => [
                    "Content-Type" => "application/json",
                ],
                "body" => $params,
            ]);

            // Validate HTTP status code
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Server_Exception(
                    "Webuzo returned HTTP :status for action :action",
                    [":status" => $statusCode, ":action" => $action],
                );
            }

            $result = $response->getContent();

            // Validate response is not empty
            if (empty($result)) {
                throw new Server_Exception(
                    "Webuzo returned empty response for action :action",
                    [":action" => $action],
                );
            }

            // Parse and validate JSON response
            $data = json_decode($result);

            $this->getLog()->info(serialize($data));

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Server_Exception(
                    "Invalid JSON from Webuzo (:action): :error",
                    [
                        ":action" => $action,
                        ":error" => json_last_error_msg(),
                    ],
                );
            }

            // Check for API-level errors in response
            if ($data?->error) {
                if (is_array($data->error)) {
                    $error = implode(
                        "; ",
                        array_map(static function ($item): string {
                            if (is_scalar($item) || $item === null) {
                                return (string) $item;
                            }

                            return json_encode(
                                $item,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ) ?:
                                "Unknown error";
                        }, $data->error),
                    );
                } elseif (is_object($data->error)) {
                    $error =
                        json_encode(
                            $data->error,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ) ?:
                        "Unknown error";
                } else {
                    $error = (string) $data->error;
                }

                throw new Server_Exception("Webuzo error (:action): :error", [
                    ":action" => (string) $action,
                    ":error" => $error,
                ]);
            }

            return $data;
        } catch (Server_Exception $e) {
            // Re-throw Server_Exception instances unchanged
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions (network errors, etc.)
            throw new Server_Exception(
                "Failed to communicate with Webuzo (:action): :error",
                [":action" => $action, ":error" => $e->getMessage()],
            );
        }
    }
}
