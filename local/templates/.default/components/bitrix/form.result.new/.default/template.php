<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */

$q = $arResult['QUESTIONS'];

/** Имя input-а для вопроса: form_<тип>_<ID ответа> */
$inputName = static function (string $sid) use ($q): string {
    $a = $q[$sid]['STRUCTURE'][0];
    return 'form_' . $a['FIELD_TYPE'] . '_' . $a['ID'];
};
$val = static function (string $sid) use ($arResult, $inputName): string {
    return htmlspecialcharsbx($arResult['arrVALUES'][$inputName($sid)] ?? '');
};
$required = static fn(string $sid): bool => ($q[$sid]['REQUIRED'] ?? 'N') === 'Y';
$label = static fn(string $sid): string =>
    htmlspecialcharsbx($q[$sid]['CAPTION']) . ($q[$sid]['REQUIRED'] === 'Y' ? '*' : '');
$err = static function (string $sid) use ($arResult): string {
    return isset($arResult['FORM_ERRORS'][$sid]) ? htmlspecialcharsbx($arResult['FORM_ERRORS'][$sid]) : '';
};

// Подсказки под полями (показываются при .invalid)
$hints = [
    'NAME'    => 'Поле должно содержать не менее 3-х символов',
    'COMPANY' => 'Поле должно содержать не менее 3-х символов',
    'EMAIL'   => 'Неверный формат почты',
    'PHONE'   => 'Неверный формат телефона',
    'MESSAGE' => '',
];
$types = ['NAME' => 'text', 'COMPANY' => 'text', 'EMAIL' => 'email', 'PHONE' => 'tel'];

$isSuccess = ($arResult['isFormNote'] ?? '') === 'Y';
?>
<div class="contact-form" id="contact-form">
    <div class="contact-form__head">
        <div class="contact-form__head-title"><?= htmlspecialcharsbx($arResult['FORM_TITLE']) ?></div>
        <div class="contact-form__head-text"><?= $arResult['FORM_DESCRIPTION'] ?></div>
    </div>

<?php if ($isSuccess): ?>
    <div class="contact-form__head-text" data-form-success="Y"><?= htmlspecialcharsbx($arResult['FORM_NOTE']) ?></div>
<?php else: ?>
    <form class="contact-form__form" name="<?= $arResult['arForm']['SID'] ?>"
          action="<?= POST_FORM_ACTION_URI ?>" method="POST" novalidate>
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="WEB_FORM_ID" value="<?= (int)$arParams['WEB_FORM_ID'] ?>">
        <input type="hidden" name="web_form_submit" value="Y">

        <?php if (($arResult['isFormErrors'] ?? '') === 'Y' && !empty($arResult['FORM_ERRORS_TEXT'])): ?>
            <div class="input__notification" style="display:block"><?= $arResult['FORM_ERRORS_TEXT'] ?></div>
        <?php endif ?>

        <div class="contact-form__form-inputs">
            <?php foreach (['NAME', 'COMPANY', 'EMAIL', 'PHONE'] as $sid):
                if (!isset($q[$sid])) continue;
                $id = 'form_' . strtolower($sid);
                $serverErr = $err($sid);
                ?>
                <div class="input contact-form__input"><label class="input__label" for="<?= $id ?>">
                    <div class="input__label-text"><?= $label($sid) ?></div>
                    <input class="input__input<?= $serverErr ? ' invalid' : '' ?>"
                           type="<?= $types[$sid] ?>" id="<?= $id ?>"
                           name="<?= $inputName($sid) ?>" value="<?= $val($sid) ?>"
                           data-validate="<?= strtolower($sid) ?>"
                           <?php if ($sid === 'PHONE'): ?>maxlength="12" x-autocompletetype="phone-full"
                           data-inputmask="'mask': '+79999999999', 'clearIncomplete': 'true'"<?php endif ?>
                           <?= $required($sid) ? 'required' : '' ?>>
                    <div class="input__notification"><?= $serverErr ?: $hints[$sid] ?></div>
                </label></div>
            <?php endforeach ?>
        </div>

        <?php if (isset($q['MESSAGE'])): ?>
            <div class="contact-form__form-message">
                <div class="input"><label class="input__label" for="form_message">
                    <div class="input__label-text"><?= $label('MESSAGE') ?></div>
                    <textarea class="input__input" id="form_message"
                              name="<?= $inputName('MESSAGE') ?>"><?= $val('MESSAGE') ?></textarea>
                    <div class="input__notification"></div>
                </label></div>
            </div>
        <?php endif ?>

        <div class="contact-form__bottom">
            <div class="contact-form__bottom-policy">Нажимая &laquo;Отправить&raquo;, Вы&nbsp;подтверждаете, что
                ознакомлены, полностью согласны и&nbsp;принимаете условия &laquo;Согласия на&nbsp;обработку
                персональных данных&raquo;.
            </div>
            <button class="form-button contact-form__bottom-button" type="submit"
                    data-success="Отправлено" data-error="Ошибка отправки">
                <div class="form-button__title"><?= htmlspecialcharsbx($arResult['arForm']['BUTTON'] ?: 'Оставить заявку') ?></div>
            </button>
        </div>
    </form>
<?php endif ?>
</div>
