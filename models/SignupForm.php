<?php
namespace app\models;

use Yii;
use yii\base\Model;

class SignupForm extends Model
{
    public $username;
    public $email;
    public $password;
    public $password_repeat;
    public $first_name;
    public $last_name;

    public function rules()
    {
        return [
            [['username', 'password', 'email', 'first_name', 'last_name'], 'required'],
            ['email', 'email'],
            ['password_repeat', 'compare', 'compareAttribute' => 'password'],
        ];
    }

    public function signup()
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new Users();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->first_name = $this->first_name;
        $user->last_name = $this->last_name;
        $user->password_hash = Yii::$app->security->generatePasswordHash($this->password);
        $user->avatar_color = '#'.substr(md5(rand()), 0, 6); // Генерация случайного цвета

        return $user->save() ? $user : null;
    }
}