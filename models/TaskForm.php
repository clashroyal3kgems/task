<?php
namespace app\models;

use Yii;
use yii\base\Model;

class TaskForm extends Model
{
    public $user_id;
    public $title;
    public $description;
    public $due_date;
    public $priority;
    public $project_id;

    public function rules()
    {
        return [
            [['title', 'user_id'], 'required'],
            [['description'], 'string'],
            [['due_date'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
            [['priority'], 'in', 'range' => ['low', 'medium', 'high']],
            [['project_id'], 'integer'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $command = Yii::$app->db->createCommand();
        return $command->insert('tasks', [
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date,
            'priority' => $this->priority,
            'project_id' => $this->project_id,
            'created_at' => date('Y-m-d H:i:s'),
        ])->execute();
    }
}