<?php

namespace Goteo\Model\Mail;

use Goteo\Core\Model;
use Goteo\Model\Mail;
use Goteo\Application\Exception\ModelException;
use Goteo\Application\Exception\ModelNotFoundException;

/**
 * Theses classes retrieves stats from MailStats generated
 */
class StatsCollector {
    private $mail;
    private $sender;
    private $metric_list = [];

    /**
     * Creates a new StatsCollector instance
     */
    public function __construct(Mail $mail) {
        $this->mail = $mail;
        // obtain Sender (if exists)
        // as is optional, stats without a Sender will rely only in mail_stats table to get the number of receivers
        try {
            $this->sender = Sender::getFromMailId($mail->id);
        } catch(ModelNotFoundException $e) {
            $this->sender = null;
        }
    }

    /**
     * List all metrics for this email
     * @return [type] [description]
     */
    public function getAllMetrics() {
        if(empty($this->metric_list)) {
            $values = array(':mail_id' => $this->mail->id);
            $sql = "SELECT DISTINCT(metric_id) as metric_id FROM mail_stats WHERE mail_stats.mail_id = :mail_id";
            if($query = Model::query($sql, $values)) {
                foreach($query->fetchAll(\PDO::FETCH_CLASS) as $ob) {
                    $metric = Metric::get($ob->metric_id);
                    $this->metric_list[$metric->id] = $this->getMetricCollector($metric);
                }
            }
        }
        return $this->metric_list;
    }

    /**
     * Gets useful data for a metric
     * @param  string $metric_val The metric to read stats of
     * @return MetricCollector instance
     */
    public function getMetricCollector(Metric $metric) {
        if(empty($this->metrics[$metric->id])) {
            // Count total sendings, from mailer_send if exists sender
            $values = array(':mail_id' => $this->mail->id, ':metric_id' => $metric->id);
            if($this->sender) {
                $values[':sender_id'] = $this->sender->id;
                $total_sql = "SELECT COUNT(*) FROM mailer_send WHERE mailer_send.mailing = :sender_id";
            }
            else {
                $total_sql = "SELECT COUNT(*) FROM mail_stats WHERE mail_stats.mail_id = :mail_id AND mail_stats.metric_id = :metric_id";
            }
            // Entries with hits
            $non_zero_sql = "SELECT COUNT(*) FROM mail_stats WHERE mail_stats.mail_id = :mail_id AND mail_stats.metric_id = :metric_id
                             AND counter>0";
            $sql = "SELECT ($total_sql) as total, ($non_zero_sql) as non_zero";
            // echo \sqldbg($sql, $values);
            if($query = Model::query($sql, $values)) {
                $this->metrics[$metric->id] = $query->fetchObject('\Goteo\Model\Mail\MetricCollector', [$metric]);
            }
            else {
                $this->metrics[$metric->id] = new MetricCollector($metric);
            }
        }
        return $this->metrics[$metric->id];

    }
    /**
     * Handy method for retrieving EMAIL_OPENED method
     * @param  Metric $metric_val [description]
     * @return [type]             [description]
     */
    public function getEmailOpenedCollector() {
        $metric = Metric::getMetric('EMAIL_OPENED');
        return $this->getMetricCollector($metric);
    }
}

class MetricCollector {
    public $metric;
    public $total = 0;
    public $non_zero = 0;

    public function __construct(Metric $metric) {
        $this->metric = $metric;
    }
    public function getPercent() {
        if($this->total)
            return 100 * $this->non_zero / $this->total;
        return 0;
    }
}
