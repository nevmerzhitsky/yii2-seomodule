<?php
namespace nevmerzhitsky\seomodule;
use yii\web\ErrorHandler;
use nevmerzhitsky\seomodule\models\SeoRedirects;

/**
 * Redirects from one route to another.
 *
 * @package nevmerzhitsky\seomodule
 * @author Max Voronov <v0id@list.ru>
 */
class Redirect extends ErrorHandler {

    public function handleException ($exception) {
        $redirectModel = SeoRedirects::find()->where(
            [
                'old_url' => Yii::$app->request->url
            ])
            ->asArray()
            ->one();

        if (!empty($redirectModel)) {
            $redirectStatus = ($redirectModel['status'] == 302) ? 302 : 301;
            header("Location: " . $redirectModel['new_url'], true,
                $redirectStatus);
            exit();
        }

        parent::handleException($exception);
    }
}
