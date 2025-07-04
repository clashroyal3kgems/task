<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\TaskForm $model */

$this->title = 'Создание задачи';
?>

<div class="task-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'title') ?>
    <?= $form->field($model, 'description')->textarea() ?>
    <?= $form->field($model, 'project_id')->input('number') ?>
    <?= $form->field($model, 'priority')->dropDownList([
        'low' => 'Низкий',
        'medium' => 'Средний',
        'high' => 'Высокий',
    ]) ?>
    <?= $form->field($model, 'date')->input('date') ?>

    <div id="subtasks-container">
        <?= $form->field($model, 'subtasks[]')->textInput(['placeholder' => 'Подзадача']) ?>
    </div>

    <button type="button" class="btn btn-secondary" onclick="addSubtask()">Добавить подзадачу</button>

    <br><br>
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<JS
function addSubtask() {
    const input = '<input type="text" class="form-control my-1" name="TaskForm[subtasks][]" placeholder="Подзадача">';
    $('#subtasks-container').append(input);
}
JS);
?>
