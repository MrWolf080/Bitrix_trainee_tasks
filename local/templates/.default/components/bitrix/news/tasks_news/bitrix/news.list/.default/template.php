<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */
$this->setFrameMode(true);
?>
<div class="article-rows">
    <?php if ($arParams["DISPLAY_TOP_PAGER"]): ?>
        <?= $arResult["NAV_STRING"] ?>
    <?php endif; ?>

    <?php foreach ($arResult["ITEMS"] as $arItem): ?>
        <?php
        $this->AddEditAction($arItem["ID"], $arItem["EDIT_LINK"], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
        $this->AddDeleteAction($arItem["ID"], $arItem["DELETE_LINK"], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => "Удалить элемент?"));
        $hasLink = !$arParams["HIDE_LINK_WHEN_NO_DETAIL"] || ($arItem["DETAIL_TEXT"] && $arResult["USER_HAVE_ACCESS"]);
        ?>
        <div class="article-row" id="<?= $this->GetEditAreaId($arItem["ID"]) ?>">
            <?php if ($arItem["NAME"]): ?>
                <div class="article-row__title">
                    <?php if ($hasLink): ?>
                        <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>"><?= $arItem["NAME"] ?></a>
                    <?php else: ?>
                        <?= $arItem["NAME"] ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($arParams["DISPLAY_DATE"] != "N" && $arItem["DISPLAY_ACTIVE_FROM"]): ?>
                <div class="article-row__date"><?= $arItem["DISPLAY_ACTIVE_FROM"] ?></div>
            <?php endif; ?>

            <div class="article-row__content">
                <?php if ($arParams["DISPLAY_PICTURE"] != "N" && is_array($arItem["PREVIEW_PICTURE"])): ?>
                    <div class="article-row__image">
                        <?php if ($hasLink): ?><a href="<?= $arItem["DETAIL_PAGE_URL"] ?>"><?php endif; ?>
                            <img src="<?= $arItem["PREVIEW_PICTURE"]["SRC"] ?>"
                                 alt="<?= htmlspecialcharsbx($arItem["PREVIEW_PICTURE"]["ALT"] ?: $arItem["NAME"]) ?>"
                                 title="<?= htmlspecialcharsbx($arItem["PREVIEW_PICTURE"]["TITLE"] ?: $arItem["NAME"]) ?>"
                                 loading="lazy">
                        <?php if ($hasLink): ?></a><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="article-row__text">
                    <?php if ($arParams["DISPLAY_PREVIEW_TEXT"] != "N" && $arItem["PREVIEW_TEXT"]): ?>
                        <div class="block-content"><?= $arItem["PREVIEW_TEXT"] ?></div>
                    <?php endif; ?>
                    <?php if ($hasLink): ?>
                        <a class="article-row__button" href="<?= $arItem["DETAIL_PAGE_URL"] ?>">Подробнее</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($arParams["DISPLAY_BOTTOM_PAGER"]): ?>
        <?= $arResult["NAV_STRING"] ?>
    <?php endif; ?>
</div>
