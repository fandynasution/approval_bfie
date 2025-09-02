<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Mail\SendPLRevisionMail;

class PlBudgetRevisionController extends Controller
{
    public function processModule($data)
    {
        $amount = number_format( $data["amount"] , 2 , '.' , ',' );

        $list_of_approve = explode('; ',  $data["approve_exist"]);
        $approve_data = [];
        foreach ($list_of_approve as $approve) {
            $approve_data[] = $approve;
        }

        $dataArray = array(
            'descs'         => $data["descs"],
            'entity_name'   => $data["entity_name"],
            'project_name'  => $data["project_name"],
            'amount'        => $amount,
            'user_name'     => $data["user_name"],
            'sender'        => $data["sender"],
            'module'        => $data["module"],
            'doc_no'        => $data["doc_no"],
            'approve_list'  => $approve_data,
            'clarify_user'  => $data['clarify_user'],
            'clarify_email' => $data['clarify_email'],
            'sender_addr'   => $data['sender_addr'],
            'body'          => "Please approve Revision RAB Budget No. ".$data['doc_no']." project ".$data["project_name"]. " with Amount ".$amount,
            'subject'       => "Need Approval for Revision RAB Budget No. ".$data['doc_no'],
        );

        $data2Encrypt = array(
            'entity_cd'     => $data["entity_cd"],
            'project_no'    => $data["project_no"],
            'email_address' => $data["email_addr"],
            'level_no'      => $data["level_no"],
            'doc_no'        => $data["doc_no"],
            'trx_type'      => $data["trx_type"],
            'user_id'       => $data["user_id"],
            'type'          => 'R',
            'type_module'   => 'PL',
            'text'          => 'Budget Revision'
        );  

        // Melakukan enkripsi pada $dataArray
        $encryptedData = Crypt::encrypt($data2Encrypt);
    
        try {
            $emailAddresses = strtolower($data["email_addr"]);
            $doc_no = $data["doc_no"];
            $entity_cd = $data["entity_cd"];
        
            // Check if email addresses are provided and not empty
            if (!empty($emailAddresses)) {
                $emails = is_array($emailAddresses) ? $emailAddresses : [$emailAddresses];
                
                foreach ($emails as $email) {
                    // Check if the email has been sent before for this document
                    $cacheKey = 'email_sent_' . md5($doc_no . '_' . $entity_cd . '_' . $email);
                    if (!Cache::has($cacheKey)) {
                        // Send email
                        Mail::to($email)->send(new SendPLRevisionMail($encryptedData, $dataArray));
        
                        // Mark email as sent
                        Cache::store('mail_app')->put($cacheKey, true, now()->addHours(24));
                    }
                }
                
                $sentTo = is_array($emailAddresses) ? implode(', ', $emailAddresses) : $emailAddresses;
                Log::channel('sendmailapproval')->info('Email doc_no ' . $doc_no . ' Entity ' . $entity_cd . ' berhasil dikirim ke: ' . $sentTo);
                return "Email berhasil dikirim ke: " . $sentTo;
            } else {
                Log::channel('sendmail')->warning("Tidak ada alamat email yang diberikan");
                Log::channel('sendmail')->info($doc_no);
                return "Tidak ada alamat email yang diberikan";
            }
        } catch (\Exception $e) {
            Log::channel('sendmail')->error('Gagal mengirim email: ' . $e->getMessage());
            return "Gagal mengirim email: " . $e->getMessage();
        }
    }

    public function update($status, $encrypt, $reason)
    {
        $data = Crypt::decrypt($encrypt);

        $descstatus = " ";
        $imagestatus = " ";

        $msg = " ";
        $msg1 = " ";
        $notif = " ";
        $st = " ";
        $image = " ";

        if ($status == "A") {
            $descstatus = "Approved";
            $imagestatus = "approved.png";
        } else if ($status == "R") {
            $descstatus = "Revised";
            $imagestatus = "revise.png";
        } else {
            $descstatus = "Cancelled";
            $imagestatus = "reject.png";
        }
        $pdo = DB::connection('BTID')->getPdo();
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_pl_budget_revision ?, ?, ?, ?, ?, ?, ?;");
        $sth->bindParam(1, $data["entity_cd"]);
        $sth->bindParam(2, $data["project_no"]);
        $sth->bindParam(3, $data["doc_no"]);
        $sth->bindParam(4, $data["trx_type"]);
        $sth->bindParam(5, $status);
        $sth->bindParam(6, $data["level_no"]);
        $sth->bindParam(7, $data["user_id"]);
        $sth->execute();
        if ($sth == true) {
            $msg = "You Have Successfully ".$descstatus." the Revision RAB Budget No. ".$data["doc_no"];
            $notif = $descstatus." !";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You Failed to ".$descstatus." the Revision RAB Budget No.".$data["doc_no"];
            $notif = 'Fail to '.$descstatus.' !';
            $st = 'OK';
            $image = "reject.png";
        }
        $msg1 = array(
            "Pesan" => $msg,
            "St" => $st,
            "notif" => $notif,
            "image" => $image
        );
        return view("email.after", $msg1);
    }
}
