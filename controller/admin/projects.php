<?php

namespace Goteo\Controller\Admin {

    use Goteo\Core\View,
        Goteo\Core\Redirection,
        Goteo\Core\Error,
		Goteo\Library\Text,
		Goteo\Library\Feed,
        Goteo\Library\Message,
        Goteo\Model;

    class Projects {

        public static function process ($action = 'list', $id = null, $filters = array()) {
            
            $log_text = null;
            $errors = array();

            // multiples usos
            $nodes = Model\Node::getList();

            if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['id'])) {

                $projData = Model\Project::get($_POST['id']);
                if (empty($projData->id)) {
                    Message::Error('El proyecto '.$_POST['id'].' no existe');
                    break;
                }

                if (isset($_POST['save-dates'])) {
                    $fields = array(
                        'created',
                        'updated',
                        'published',
                        'success',
                        'closed',
                        'passed'
                        );

                    $set = '';
                    $values = array(':id' => $projData->id);

                    foreach ($fields as $field) {
                        if ($set != '') $set .= ", ";
                        $set .= "`$field` = :$field ";
                        if (empty($_POST[$field]) || $_POST[$field] == '0000-00-00')
                            $_POST[$field] = null;

                        $values[":$field"] = $_POST[$field];
                    }

                    if ($set == '') {
                        break;
                    }

                    try {
                        $sql = "UPDATE project SET " . $set . " WHERE id = :id";
                        if (Model\Project::query($sql, $values)) {
                            $log_text = 'El admin %s ha <span class="red">tocado las fechas</span> del proyecto '.$projData->name.' %s';
                        } else {
                            $log_text = 'Al admin %s le ha <span class="red">fallado al tocar las fechas</span> del proyecto '.$projData->name.' %s';
                        }
                    } catch(\PDOException $e) {
                        Message::Error("Ha fallado! " . $e->getMessage());
                    }
                } elseif (isset($_POST['save-accounts'])) {

                    $accounts = Model\Project\Account::get($projData->id);
                    $accounts->bank = $_POST['bank'];
                    $accounts->bank_owner = $_POST['bank_owner'];
                    $accounts->paypal = $_POST['paypal'];
                    $accounts->paypal_owner = $_POST['paypal_owner'];
                    if ($accounts->save($errors)) {
                        Message::Info('Se han actualizado las cuentas del proyecto '.$projData->name);
                    } else {
                        Message::Error(implode('<br />', $errors));
                    }

                } elseif (isset($_POST['save-node'])) {

                    if (!isset($nodes[$_POST['node']])) {
                        Message::Error('El nodo '.$_POST['node'].' no existe! ');
                    } else {

                        $values = array(':id' => $projData->id, ':node' => $_POST['node']);
                        try {
                            $sql = "UPDATE project SET node = :node WHERE id = :id";
                            if (Model\Project::query($sql, $values)) {
                                $log_text = 'El admin %s ha <span class="red">movido al nodo '.$nodes[$_POST['node']].'</span> el proyecto '.$projData->name.' %s';
                            } else {
                                $log_text = 'Al admin %s le ha <span class="red">fallado al mover al nodo '.$nodes[$_POST['node']].'</span> el proyecto '.$projData->name.' %s';
                            }
                        } catch(\PDOException $e) {
                            Message::Error("Ha fallado! " . $e->getMessage());
                        }
                        
                    }

                }

            }

            /*
             * switch action,
             * proceso que sea,
             * redirect
             *
             */
            if (isset($id)) {
                $project = Model\Project::get($id);
            }
            switch ($action) {
                case 'review':
                    // pasar un proyecto a revision
                    if ($project->ready($errors)) {
                        $redir = '/admin/reviews/add/'.$project->id;
                        $log_text = 'El admin %s ha pasado el proyecto %s al estado <span class="red">Revisión</span>';
                    } else {
                        $log_text = 'Al admin %s le ha fallado al pasar el proyecto %s al estado <span class="red">Revisión</span>';
                    }
                    break;
                case 'publish':
                    // poner un proyecto en campaña
                    if ($project->publish($errors)) {
                        $log_text = 'El admin %s ha pasado el proyecto %s al estado <span class="red">en Campaña</span>';
                    } else {
                        $log_text = 'Al admin %s le ha fallado al pasar el proyecto %s al estado <span class="red">en Campaña</span>';
                    }
                    break;
                case 'cancel':
                    // descartar un proyecto por malo
                    if ($project->cancel($errors)) {
                        $log_text = 'El admin %s ha pasado el proyecto %s al estado <span class="red">Descartado</span>';
                    } else {
                        $log_text = 'Al admin %s le ha fallado al pasar el proyecto %s al estado <span class="red">Descartado</span>';
                    }
                    break;
                case 'enable':
                    // si no está en edición, recuperarlo
                    if ($project->enable($errors)) {
                        $log_text = 'El admin %s ha pasado el proyecto %s al estado <span class="red">Edición</span>';
                    } else {
                        $log_text = 'Al admin %s le ha fallado al pasar el proyecto %s al estado <span class="red">Edición</span>';
                    }
                    break;
                case 'complete':
                    // dar un proyecto por financiado manualmente
                    if ($project->succeed($errors)) {
                        $log_text = 'El admin %s ha pasado el proyecto %s al estado <span class="red">Financiado</span>';
                    } else {
                        $log_text = 'Al admin %s le ha fallado al pasar el proyecto %s al estado <span class="red">Financiado</span>';
                    }
                    break;
                case 'fulfill':
                    // marcar que el proyecto ha cumplido con los retornos colectivos
                    if ($project->satisfied($errors)) {
                        $log_text = 'El admin %s ha pasado el proyecto %s al estado <span class="red">Retorno cumplido</span>';
                    } else {
                        $log_text = 'Al admin %s le ha fallado al pasar el proyecto %s al estado <span class="red">Retorno cumplido</span>';
                    }
                    break;
            }

            if (isset($log_text)) {
                // Evento Feed
                $log = new Feed();
                $log->populate('Cambio estado/fechas/cuentas/nodo de un proyecto desde el admin', '/admin/projects',
                    \vsprintf($log_text, array(
                    Feed::item('user', $_SESSION['user']->name, $_SESSION['user']->id),
                    Feed::item('project', $project->name, $project->id)
                )));
                $log->doAdmin('admin');

                Message::Info($log->html);
                if (!empty($errors)) {
                    Message::Error(implode('<br />', $errors));
                }

                if ($action == 'publish') {
                    // si es publicado, hay un evento público
                    $log->populate($project->name, '/project/'.$project->id, Text::html('feed-new_project'), $project->gallery[0]->id);
                    $log->setTarget($project->id);
                    $log->doPublic('projects');
                }

                unset($log);

                if (empty($redir)) {
                    throw new Redirection('/admin/projects/list');
                } else {
                    throw new Redirection($redir);
                }
            }

            if ($action == 'report') {
                // informe financiero
                // Datos para el informe de transacciones correctas
                $reportData = Model\Invest::getReportData($project->id, $project->status, $project->round, $project->passed);

                return new View(
                    'view/admin/index.html.php',
                    array(
                        'folder' => 'projects',
                        'file' => 'report',
                        'project' => $project,
                        'reportData' => $reportData
                    )
                );
            }

            if ($action == 'dates') {
                // cambiar fechas
                return new View(
                    'view/admin/index.html.php',
                    array(
                        'folder' => 'projects',
                        'file' => 'dates',
                        'project' => $project
                    )
                );
            }

            if ($action == 'accounts') {

                $accounts = Model\Project\Account::get($project->id);

                // cambiar fechas
                return new View(
                    'view/admin/index.html.php',
                    array(
                        'folder' => 'projects',
                        'file' => 'accounts',
                        'project' => $project,
                        'accounts' => $accounts
                    )
                );
            }

            if ($action == 'move') {
                // cambiar el nodo
                return new View(
                    'view/admin/index.html.php',
                    array(
                        'folder' => 'projects',
                        'file' => 'move',
                        'project' => $project,
                        'nodes' => $nodes
                    )
                );
            }


            if (!empty($filters['filtered'])) {
                $projects = Model\Project::getList($filters, $_SESSION['admin_node']);
            } else {
                $projects = array();
            }
            $status = Model\Project::status();
            $categories = Model\Project\Category::getAll();
            // la lista de nodos la hemos cargado arriba
            $orders = array(
                'name' => 'Nombre',
                'updated' => 'Enviado a revision'
            );

            return new View(
                'view/admin/index.html.php',
                array(
                    'folder' => 'projects',
                    'file' => 'list',
                    'projects' => $projects,
                    'filters' => $filters,
                    'status' => $status,
                    'categories' => $categories,
                    'user' => $user,
                    'nodes' => $nodes,
                    'orders' => $orders
                )
            );
            
        }

    }

}
