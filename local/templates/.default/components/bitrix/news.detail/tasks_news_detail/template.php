<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */
$this->setFrameMode(true);
?>
<div class="article-card">
    <?php if ($arResult["NAME"]): ?>
        <div class="article-card__title"><?= $arResult["NAME"] ?></div>
    <?php endif; ?>

    <?php if ($arParams["DISPLAY_DATE"] != "N" && $arResult["DISPLAY_ACTIVE_FROM"]): ?>
        <div class="article-card__date"><?= $arResult["DISPLAY_ACTIVE_FROM"] ?></div>
    <?php endif; ?>

    <div class="article-card__content">
        <?php if ($arParams["DISPLAY_PICTURE"] != "N" && is_array($arResult["DETAIL_PICTURE"])): ?>
            <div class="article-card__image">
                <img src="<?= $arResult["DETAIL_PICTURE"]["SRC"] ?>"
                     alt="<?= htmlspecialcharsbx($arResult["DETAIL_PICTURE"]["ALT"] ?: $arResult["NAME"]) ?>"
                     title="<?= htmlspecialcharsbx($arResult["DETAIL_PICTURE"]["TITLE"] ?: $arResult["NAME"]) ?>">
            </div>
        <?php endif; ?>

        <div class="article-card__text">
            <div class="block-content">
                <?php if ($arResult["NAV_RESULT"]): ?>
                    <?php if ($arParams["DISPLAY_TOP_PAGER"]): ?><?= $arResult["NAV_STRING"] ?><?php endif; ?>
                    <?= $arResult["NAV_TEXT"] ?>
                    <?php if ($arParams["DISPLAY_BOTTOM_PAGER"]): ?><?= $arResult["NAV_STRING"] ?><?php endif; ?>
                <?php elseif ($arResult["DETAIL_TEXT"] <> ''): ?>
                    <?= $arResult["DETAIL_TEXT"] ?>
                <?php else: ?>
                    <?= $arResult["PREVIEW_TEXT"] ?>
                <?php endif; ?>
            </div>

            <a class="article-card__button" href="<?= $arResult["LIST_PAGE_URL"] ?>">Назад к новостям</a>
        </div>
    </div>
</div>
