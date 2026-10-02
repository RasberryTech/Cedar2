<?php
require_once('lib/htm.php');

if (empty($_SESSION['signed_in'])) {

    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <script src="/assets/js/pace.min.js"></script>
            <script src="/assets/js/jquery-3.3.1.min.js"></script>
            <script src="/assets/js/jquery.pjax.js"></script>
            <script src="/assets/js/favico.js"></script>
            <script src="/assets/js/yeah.js"></script>

            <meta name="viewport" content="width=device-width,minimum-scale=1, maximum-scale=1">
            <link rel="stylesheet" type="text/css" href="/assets/css/login.css">
            <title>Create an account</title>
        </head>

        <body>
        <div class="hb-contents-wrapper">

            <div class="hb-container hb-l-inside">
                <h2>Sign Up</h2>
                <p>Create a User ID for Cedar 2.</p>
            </div>

            <form method="post">

                <div class="hb-container hb-l-inside-half hb-mg-top-none">

                    <div class="auth-input-double">

                        <label>
                            <input
                                type="text"
                                name="username"
                                maxlength="16"
                                title="Cedar 2 ID"
                                placeholder="User ID"
                                value=""
                                required>
                        </label>

                        <label>
                            <input
                                type="password"
                                name="password"
                                maxlength="255"
                                title="Password"
                                placeholder="Password"
                                required>
                        </label>

                        <label>
                            <input
                                type="password"
                                name="confirm_password"
                                maxlength="255"
                                title="Password"
                                placeholder="Confirm Password"
                                required>
                        </label>

                        <label>
                            <input
                                type="text"
                                name="name"
                                maxlength="16"
                                title="Name"
                                placeholder="Name"
                                value=""
                                required>
                        </label>

                        <label>
                            <input
                                type="text"
                                name="pnid"
                                maxlength="16"
                                title="PNID"
                                placeholder="Pretendo Network ID"
                                value=""
                                required>
                        </label>

                    </div>

                    <p class="note" style="text-align:center;">
                        Your PNID is used to fetch your Wii U Mii.
                    </p>

                    <input
                        type="submit"
                        name="submit"
                        class="hb-btn hb-is-decide"
                        style="margin-top: 4px;"
                        id="btn_text"
                        value="Sign Up">

                </div>

            </form>
        </div>

        </body>
        </html>

        <?php

    } else {

        $errors = array();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $pnid = trim($_POST['pnid'] ?? '');

        /*
         * Validate User ID
         */

        if (empty($username)) {
            $errors[] = 'User ID cannot be empty.';
        }

        if (strlen($username) > 16) {
            $errors[] = 'User ID cannot be longer than 16 characters.';
        }

        if (preg_match('/([%\$#\*\/\s]+)/', $username)) {
            $errors[] = 'User ID cannot contain special characters or spaces.';
        }

        /*
         * Validate password
         */

        if (empty($password)) {
            $errors[] = 'Password cannot be empty.';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        /*
         * Validate name
         */

        if (empty($name)) {
            $errors[] = 'Name cannot be empty.';
        }

        if (strlen($name) > 16) {
            $errors[] = 'Name cannot be longer than 16 characters.';
        }

        /*
         * Validate PNID
         */

        if (empty($pnid)) {
            $errors[] = 'PNID cannot be empty.';
        }

        if (strlen($pnid) > 16) {
            $errors[] = 'PNID cannot be longer than 16 characters.';
        }

        /*
         * Check User ID
         */

        if (empty($errors)) {

            $search_user = $dbc->prepare(
                'SELECT user_id FROM users WHERE user_name = ? LIMIT 1'
            );

            $search_user->bind_param('s', $username);
            $search_user->execute();

            $user_result = $search_user->get_result();

            if ($user_result->num_rows > 0) {
                $errors[] = 'User ID already exists.';
            }
        }

        /*
         * Check PNID
         */

        if (empty($errors)) {

            $search_pnid = $dbc->prepare(
                'SELECT user_id FROM users WHERE pnid = ? LIMIT 1'
            );

            $search_pnid->bind_param('s', $pnid);
            $search_pnid->execute();

            $pnid_result = $search_pnid->get_result();

            if ($pnid_result->num_rows > 0) {
                $errors[] = 'That PNID is already being used.';
            }
        }

        /*
         * Fetch Mii information from PNID
         */

        $face = '';

        if (empty($errors)) {

            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_URL =>
                    'https://mii-unsecure.ariankordi.net/mii_data/' .
                    rawurlencode($pnid) .
                    '?api_id=1',

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20
            ));

            $response = curl_exec($ch);

            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($response === false) {

                $errors[] = 'Could not contact the Mii service.';

                if (!empty($curl_error)) {
                    error_log('Mii service error: ' . $curl_error);
                }

            } elseif ($http_code != 200) {

                $errors[] = 'The Mii service returned an error.';

            } else {

                /*
                 * The endpoint returns JSON containing:
                 *
                 * {
                 *   "data": "...",
                 *   "name": "...",
                 *   "pid": ...,
                 *   "user_id": "..."
                 * }
                 */

                $mii = json_decode($response, true);

                if (!is_array($mii) || empty($mii['data'])) {

                    $errors[] = 'No Mii data detected.';

                } else {

                    /*
                     * Keep the Mii data from the service.
                     * printFace() will add the Cedar 2 emotion.
                     */

                    $face =
                        'https://mii-unsecure.ariankordi.net/miis/image.png' .
                        '?data=' . rawurlencode($mii['data']) .
                        '&width=270' .
                        '&type=face' .
                        '&shaderType=wiiu';
                }
            }
        }

        /*
         * Show errors
         */

        if (!empty($errors)) {

            echo '<script type="text/javascript">alert(' .
                json_encode($errors[0]) .
                ');</script>';

            echo '<META HTTP-EQUIV="refresh" content="0;URL=/signup">';

            exit;
        }

        /*
         * Create account
         */

        $username_db = htmlspecialchars(
            $username,
            ENT_QUOTES,
            'UTF-8'
        );

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $new_user = $dbc->prepare(
            'INSERT INTO users
            (user_name, user_pass, nickname, user_face, pnid, date_created, ip)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)'
        );

        $new_user->bind_param(
            'ssssss',
            $username_db,
            $password_hash,
            $name,
            $face,
            $pnid,
            $ip
        );

        if (!$new_user->execute()) {

            if ($dbc->errno == 1062) {
                exit('That User ID or PNID is already being used.');
            }

            exit('There was an error creating your account.');
        }

        /*
         * Get newly-created user ID
         */

        $get_user = $dbc->prepare(
            'SELECT user_id
             FROM users
             WHERE user_name = ?
             LIMIT 1'
        );

        $get_user->bind_param('s', $username_db);
        $get_user->execute();

        $user_result = $get_user->get_result();

        if ($user_result->num_rows == 0) {

            printHeader('');
            exit(
                '<br>There was an error creating your account. Please try again.'
            );
        }

        $user = $user_result->fetch_assoc();

        /*
         * Create profile
         */

        $new_profile = $dbc->prepare(
            'INSERT INTO profiles (user_id) VALUES (?)'
        );

        $new_profile->bind_param(
            'i',
            $user['user_id']
        );

        $new_profile->execute();

        /*
         * Sign the user in
         */

        $_SESSION['signed_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];

        echo '<META HTTP-EQUIV="refresh" content="0;URL=/">';
    }
}
?>
