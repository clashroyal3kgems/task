<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */

/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Вход';
?>
<style>
    :root {
        --primary: #5e35b1;
        --primary-light: #7e57c2;
        --primary-dark: #4527a0;
        --secondary: #26a69a;
        --accent: #ff7043;
        --text: #2d3748;
        --text-light: #4a5568;
        --text-lighter: #718096;
        --bg: #f7fafc;
        --bg-panel: #ffffff;
        --border: #e2e8f0;
        --success: #48bb78;
        --warning: #ed8936;
        --danger: #f56565;
        --info: #4299e1;
        --low-priority: #a0aec0;
        --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .login-container {
        max-width: 500px;
        margin:  100px auto 100px auto;
        padding: 2rem;
        background-color: var(--bg-panel);
        border-radius: 0.75rem;
        box-shadow: var(--shadow-md);
    }

    .login-title {
        color: var(--primary);
        text-align: center;
        margin-bottom: 1.5rem;
        font-size: 1.75rem;
        font-weight: 600;
    }

    .login-description {
        color: var(--text-light);
        text-align: center;
        margin-bottom: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text);
        font-weight: 500;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        background-color: var(--bg-panel);
        font-size: 1rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-light);
        box-shadow: 0 0 0 3px rgba(94, 53, 177, 0.1);
    }

    .invalid-feedback {
        color: var(--danger);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
        border: none;
        border-radius: 0.5rem;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s;
        width: 100%;
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
    }

    .btn-secondary {
        background-color: var(--bg);
        color: var(--primary);
        border: 1px solid var(--primary);
        border-radius: 0.5rem;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
        margin-top: 1rem;
        text-align: center;
        display: block;
        text-decoration: none;
    }

    .btn-secondary:hover {
        background-color: rgba(94, 53, 177, 0.1);
    }

    .checkbox-container {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .checkbox-input {
        width: 18px;
        height: 18px;
        margin-right: 0.75rem;
        accent-color: var(--primary);
    }

    .checkbox-label {
        color: var(--text-light);
    }

    .login-hint {
        color: var(--text-lighter);
        font-size: 0.875rem;
        text-align: center;
        margin-top: 1.5rem;
        line-height: 1.5;
    }

    .login-hint code {
        background-color: var(--bg);
        padding: 0.2rem 0.4rem;
        border-radius: 0.25rem;
        font-family: monospace;
        color: var(--danger);
    }

    .register-prompt {
        text-align: center;
        margin-top: 1.5rem;
        color: var(--text-light);
    }

    .register-link {
        color: var(--primary);
        font-weight: 500;
        text-decoration: none;
        transition: color 0.2s;
    }

    .register-link:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
</style>

<div class="login-container">
    <h1 class="login-title"><?= Html::encode($this->title) ?></h1>

    <p class="login-description">Пожалуйста, заполните следующие поля для входа:</p>

    <?php $form = ActiveForm::begin([
        'id' => 'login-form',
        'fieldConfig' => [
            'template' => "{label}\n{input}\n{error}",
            'labelOptions' => ['class' => 'form-label'],
            'inputOptions' => ['class' => 'form-control'],
            'errorOptions' => ['class' => 'invalid-feedback'],
        ],
    ]); ?>

    <?= $form->field($model, 'username')->textInput(['autofocus' => true])->label('Имя пользователя') ?>

    <?= $form->field($model, 'password')->passwordInput()->label('Пароль') ?>

    <div class="checkbox-container">
        <?= $form->field($model, 'rememberMe')->checkbox([
            'template' => "{input} {label}",
            'labelOptions' => ['class' => 'checkbox-label'],
            'inputOptions' => ['class' => 'checkbox-input']
        ])->label('Запомнить меня') ?>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Войти', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
        <a href="<?= Yii::$app->urlManager->createUrl(['site/signup']) ?>" class="btn-secondary">
            Регистрация
        </a>
    </div>

    <?php ActiveForm::end(); ?>

    <div class="register-prompt">
        Нет аккаунта? <a href="<?= Yii::$app->urlManager->createUrl(['site/signup']) ?>" class="register-link">Зарегистрируйтесь здесь</a>
    </div>

</div>