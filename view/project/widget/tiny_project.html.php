<?php

use Goteo\Core\View,
    Goteo\Library\Text,
    Goteo\Library\Check,
    Goteo\Model\Image;

$project = $this['project'];
$url = '';
?>
<li>
    <a href="<?php echo $url ?>/project/<?php echo $project->id ?>" class="expand" target="_blank"></a>
    <div class="image">
        <?php switch ($project->tagmark) {
            case 'onrun': // "en marcha"
                echo '<div class="tagmark green">' . Text::get('regular-onrun_mark') . '</div>';
                break;
            case 'keepiton': // "aun puedes"
                echo '<div class="tagmark green">' . Text::get('regular-keepiton_mark') . '</div>';
                break;
            case 'onrun-keepiton': // "en marcha" y "aun puedes"
                  echo '<div class="tagmark green twolines"><span class="small"><strong>' . Text::get('regular-onrun_mark') . '</strong><br />' . Text::get('regular-keepiton_mark') . '</span></div>';
                break;
            case 'gotit': // "financiado"
                echo '<div class="tagmark violet">' . Text::get('regular-gotit_mark') . '</div>';
                break;
            case 'success': // "exitoso"
                echo '<div class="tagmark red">' . Text::get('regular-success_mark') . '</div>';
                break;
            case 'fail': // "caducado"
                echo '<div class="tagmark grey">' . Text::get('regular-fail_mark') . '</div>';
                break;
        } ?>

        <?php if ($project->image instanceof Image): ?>
        <a href="<?php echo $url ?>/project/<?php echo $project->id ?>"><img src="<?php echo $project->image->getLink(150, 98, true) ?>" alt="<?php echo $project->name ?>"/></a>
        <?php endif ?>
        <?php if (!empty($project->categories)): ?>
        <div class="categories"><?php $sep = ''; foreach ($project->categories as $key=>$value) :
            echo $sep.htmlspecialchars($value);
        $sep = ', '; endforeach; ?></div>
        <?php endif ?>
    </div>
    <h3 class="title"><a href="<?php echo $url ?>/project/<?php echo $project->id ?>"<?php echo $blank; ?>><?php echo htmlspecialchars(Text::recorta($project->name,50)) ?></a></h3>
    <div class="description"><?php echo empty($project->subtitle) ? Text::recorta($project->description, 100) : Text::recorta($project->subtitle, 100); ?></div>
    <h4 class="author"><?php echo Text::get('regular-by')?> <a href="<?php echo $url ?>/user/profile/<?php echo htmlspecialchars($project->user->id) ?>" target="_blank"><?php echo htmlspecialchars(Text::recorta($project->user->name,40)) ?></a></h4>
    <span class="obtained"><?php echo Text::get('project-view-metter-got'); ?></span>
    <div class="obtained">
        <strong><?php echo \amount_format($project->amount) ?> <span class="euro">&euro;</span></strong>
        <span class="percent"><?php echo $project->per_amount ?> &#37;</span>
    </div>
    <?php
    switch ($project->status) {
        case 1: // en edicion
        ?>
    <div class="days"><span><?php echo Text::get('project-view-metter-day_created'); ?></span> <?php echo date('d / m / Y', strtotime($project->created)) ?></div>
        <?php
        break;

        case 2: // enviado a revision
        ?>
    <div class="days"><span><?php echo Text::get('project-view-metter-day_updated'); ?></span> <?php echo date('d / m / Y', strtotime($project->updated)) ?></div>
        <?php
        break;

        case 4: // financiado
        case 5: // caso de exito
        ?>
    <div class="days"><span><?php echo Text::get('project-view-metter-day_success'); ?></span> <?php echo date('d / m / Y', strtotime($project->success)) ?></div>
        <?php
        break;

        case 6: // archivado
        ?>
    <div class="days"><span><?php echo Text::get('project-view-metter-day_closed'); ?></span> <?php echo date('d / m / Y', strtotime($project->closed)) ?></div>
        <?php
        break;

        default:
            if ($project->days > 2 || $project->days == 0) :
        ?>
    <div class="days"><span><?php echo Text::get('project-view-metter-days'); ?></span> <?php echo $project->days ?> <?php echo Text::get('regular-days'); ?></div>
        <?php
            else :
                $part = strtotime($project->published);
                // si primera ronda: published + 40
                // si segunda ronda: published + 80
                $plus = 40 * $project->round;
                $final_day = date('Y-m-d', mktime(0, 0, 0, date('m', $part), date('d', $part)+$plus, date('Y', $part)));
                $timeTogo = Check::time_togo($final_day,1);
        ?>
    <div class="days"><span><?php echo Text::get('project-view-metter-days'); ?></span> <?php echo $timeTogo ?></div>
        <?php
            endif;
        break;
    }
    ?>
</li>
