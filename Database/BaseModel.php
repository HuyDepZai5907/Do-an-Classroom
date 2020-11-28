    <?php
    // Import PHPMailer classes into the global namespace
    // These must be at the top of your script, not inside a function
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    // Instantiation and passing `true` enables exceptions
    // Load Composer's autoloader
    class BaseModel {
        private $db;
        function __construct() {
            $this->db = Database::open();
        }
        function generateRandomString($length = 64) {
            return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
        }

        // protected để chỉ lớp con truy xuất được
        function query($sql) {
            $result = $this->db->query($sql); // thiếu ->db
            if (!$result) {
                return array('code' => 1, 'error' => $this->db->error);
            }

            $data = array();
            while ($item = $result->fetch_assoc()) {
                array_push($data, $item);
            }
            return array('code' => 0, 'data' => $data);
        }

        function query_prepared($sql, $param) {
            $stm = $this->db->prepare($sql);
            call_user_func_array(array($stm, 'bind_param'), $param);
            if (!$stm->execute()) {
                return array('code' => 1, 'error' => $this->db->error);
            }

            $result = $stm->get_result();

            // hiện tại chỉ mới đọc 1 items, cần dùng
            // vòng lặp để đọc tất cả
            $data = array();
            if($result === true){
                return array('code' => 0, 'data' => 'success');
            }
            elseif($result === false){
                return array('code' => 0, 'data' => 'failed');
            }
            while ($item = $result->fetch_assoc()) {
                array_push($data, $item);
            }
            return array('code' => 0, 'data' => $data);
        }
        function is_exists($sql, $param){
            $stm = $this->db->prepare($sql);
            call_user_func_array(array($stm, 'bind_param'), $param);
            if (!$stm->execute()) {
                die('Query error: '.$stm->error);
            }
            $result = $stm->get_result();
            $count = $result->fetch_assoc()['count(*)'];
            if($count > 0)
                return true;
            else
                return false;
        }
        function query_prepared_nonquery($sql, $param) {
            $stm = $this->db->prepare($sql);
            call_user_func_array(array($stm, 'bind_param'), $param);
            if (!$stm->execute()) {
                return array('code' => 1, 'error' => $this->db->error);
            }
            return array('code' => 0, 'data' => 'success');
        }
        function send_activation_email($email,$token){

            $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->SMTPDebug = 0; //0 = off (for production use, No debug messages) debugging: 1 = errors and messages, 2 = messages only
                //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
                $mail->isSMTP();
                $mail->CharSet = "UTF-8"; // Send using SMTP
                $mail->Host       = 'smtp.gmail.com';                    // Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
                $mail->Username   = 'webprojectclassroom@gmail.com';                     // SMTP username
                $mail->Password   = 'tepnkvzgwljzcayy';                               // SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
                $mail->Port       = 587;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

                //Recipients
                $mail->setFrom('webprojectclassroom@gmail.com', 'Classroom');
                $mail->addAddress($email, 'Người nhận');     // Add a recipient
                /*
                $mail->addAddress('ellen@example.com');               // Name is optional
                $mail->addReplyTo('info@example.com', 'Information');
                $mail->addCC('cc@example.com');
                $mail->addBCC('bcc@example.com');
                */

                // Attachments
                //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
                //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

                // Content
                $mail->isHTML(true);                                  // Set email format to HTML
                $mail->Subject = 'Xác minh tài khoản của bạn';
                $mail->Body    = "Click <a href='http://localhost/Activate.php?email=$email&token=$token'>vào đây</a> để xác minh tài khoản của bạn";
                //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

                $mail->send();

                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        function is_email_exists($email){
            $sql = 'select username from account where email = ?';
            $stm = $this->db->prepare($sql);
            $stm->bind_param('s',$email);
            if(!$stm->execute()){
                die('Query error: '.$stm->error);
            }
            $result = $stm->get_result();
            if($result->num_rows > 0){
                return true;
            }else
                return false;
        }
        function reset_password($email){
            if(!$this->is_email_exists($email)){
                return array('code'=>1,'error'=>'Email không tồn tại');
            }
            $token = md5($email.'+'.random_int(1000,2000));
            $sql = 'update reset_token set token = ? where email = ?';
            $stm = $this->db->prepare($sql);
            $stm->bind_param('ss',$token,$email);
            if(!$stm->execute()){
                return array('code'=>2,'error'=>'Can not execute command');
            }
            if($stm->affected_rows == 0){
                $exp = time() + 3600*24;//hết hạn sau 24h
                $sql = 'insert into reset_token values(?,?,?)';
                $stm = $this->db->prepare($sql);
                $stm->bind_param('ssi',$email,$token,$exp);
                if(!$stm->execute()){
                    return array('code'=>2,'error'=>'Can not execute command');
                }
            }
            //Gửi email reset tài khoản
            $success = $this->send_resetpassword_email($email,$token);
            return array('code'=>0,'success'=>$success);
            //Chèn thành công hoặc update thành công token
        }
        function send_resetpassword_email($email,$token){

            $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->SMTPDebug = 0; //0 = off (for production use, No debug messages) debugging: 1 = errors and messages, 2 = messages only
                //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
                $mail->isSMTP();
                $mail->CharSet = "UTF-8"; // Send using SMTP
                $mail->Host       = 'smtp.gmail.com';                    // Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
                $mail->Username   = 'webprojectclassroom@gmail.com';                     // SMTP username
                $mail->Password   = 'tepnkvzgwljzcayy';                               // SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
                $mail->Port       = 587;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

                //Recipients
                $mail->setFrom('webprojectclassroom@gmail.com', 'Classroom');
                $mail->addAddress($email, 'Người nhận');     // Add a recipient
                /*
                $mail->addAddress('ellen@example.com');               // Name is optional
                $mail->addReplyTo('info@example.com', 'Information');
                $mail->addCC('cc@example.com');
                $mail->addBCC('bcc@example.com');
                */

                // Attachments
                //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
                //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

                // Content
                $mail->isHTML(true);                                  // Set email format to HTML
                $mail->Subject = 'Khôi phục mật khẩu của bạn';
                $mail->Body    = "Click <a href='http://localhost/ResetPassword.php?email=$email&token=$token'>vào đây</a> để khôi phục mật khẩu của bạn";
                //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

                $mail->send();

                return true;
            } catch (Exception $e) {
                return false;
            }
        }

    }