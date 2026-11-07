<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */
$this->setFrameMode(true);

if (empty($arResult["SECTIONS"])) {
    return;
}

$currentId   = (int)$arParams["CURRENT_SECTION_ID"];
$currentCode = (string)$arParams["CURRENT_SECTION_CODE"];
$isAll       = ($currentId === 0 && $currentCode === '');
?>
<nav class="news-sections">
    <a class="news-sections__item<?= $isAll ? ' news-sections__item_active' : '' ?>"
       href="<?= htmlspecialcharsbx($arParams["NEWS_LIST_URL"]) ?>">Все новости</a>

    <?php foreach ($arResult["SECTIONS"] as $arSection): ?>
        <?php
        $this->AddEditAction($arSection["ID"], $arSection["EDIT_LINK"], CIBlock::GetArrayByID($arSection["IBLOCK_ID"], "SECTION_EDIT"));
        $this->AddDeleteAction($arSection["ID"], $arSection["DELETE_LINK"], CIBlock::GetArrayByID($arSection["IBLOCK_ID"], "SECTION_DELETE"), array("CONFIRM" => "Удалить раздел?"));
        $isActive = ($currentId > 0 && $currentId === (int)$arSection["ID"])
            || ($currentCode !== '' && $currentCode === $arSection["CODE"]);
        ?>
        <a class="news-sections__item<?= $isActive ? ' news-sections__item_active' : '' ?>"
           id="<?= $this->GetEditAreaId($arSection["ID"]) ?>"
           href="<?= $arSection["SECTION_PAGE_URL"] ?>">
            <?= $arSection["NAME"] ?>
            <?php if ($arParams["COUNT_ELEMENTS"] === "Y" && $arSection["ELEMENT_CNT"] !== null): ?>
                <span class="news-sections__count"><?= (int)$arSection["ELEMENT_CNT"] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
