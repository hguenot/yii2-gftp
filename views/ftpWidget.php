<?php

/**
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpUnusedParameterInspection
 *
 * @var Exception $error
 * @var string $baseFolder
 * @var string[] $columns
 */

use gftp\FtpUtils;
use yii\data\ArrayDataProvider;
use yii\grid\GridView;

if ($error !== null) {
	echo '<div class="flash-error">Could not display folder content : ' . $error->getMessage() . "</div>\n";
} else {
	$dp = new ArrayDataProvider ([
			'allModels' => $files,
			'sort' => [
					'attributes' => ['filename'],
			],
			'pagination' => [
					'pageSize' => 10,
			]
	]);

	echo 'Current Working dir : ' . htmlspecialchars($baseFolder) . '<br />';
	echo GridView::widget([
			'id' => 'page-grid',
			'dataProvider' => $dp,
			'columns' => [
					[
							'header' => 'File name',
							'value' => function ($model, $key, $index, $column) {
								return FtpUtils::displayFilename($model, $column->grid->view->context);
							},
							'format' => 'html',
							'filter' => false,
							'attribute' => 'filename',
							'visible' => in_array('filename', $columns, true)
					],
					[
							'header' => 'Rights',
							'filter' => false,
							'attribute' => 'rights',
							'visible' => in_array('rights', $columns, true)
					],
					[
							'header' => 'User',
							'filter' => false,
							'attribute' => 'user',
							'visible' => in_array('user', $columns, true)
					],
					[
							'header' => 'Group',
							'filter' => false,
							'attribute' => 'group',
							'visible' => in_array('group', $columns, true)
					],
					[
							'header' => 'Modification time',
							'filter' => false,
							'attribute' => 'mdTime',
							'visible' => in_array('mdTime', $columns, true)
					],
					[
							'header' => 'Size',
							'value' => function ($model) {
								return FtpUtils::isDir($model) ? "" : $model->size;
							},
							'contentOptions' => ['style' => 'text-align: right;'],
							'filter' => false,
							'attribute' => 'size',
							'visible' => in_array('size', $columns, true)
					],
			],
	]);
}
