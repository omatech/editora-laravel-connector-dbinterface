<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class EditoraCreateMVC extends Command {

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'editora:createmvc';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create MVC';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		if (strcmp(env('APP_ENV'), 'local') == 0) {

			//Classes con NICEURL
			$classes = DB::table('omp_class_attributes')
					->join('omp_classes', 'omp_class_attributes.class_id', '=', 'omp_classes.id')
					->select('omp_class_attributes.class_id', 'omp_classes.name')->where('atri_id', '10002')->get();
			foreach ($classes as $class) {
				//Controller
				$this->createController($class);
				//Model
				$this->createModel($class);
				//View
				$this->createView($class);
			}

			//Clases sin NICEURL
			/*            $classes = DB::select("SELECT omp_class_attributes.class_id, omp_classes.name 
			  FROM omp_class_attributes
			  JOIN omp_classes ON omp_class_attributes.class_id = omp_classes.id
			  WHERE class_id
			  NOT IN (SELECT class_id FROM omp_class_attributes WHERE atri_id = 10002)
			  GROUP BY class_id");
			 */
			$classes = DB::select("select id, name from omp_classes 
																		where id not in 
																		(select class_id
																		from omp_class_attributes ca
																		where ca.atri_id=10002
																		group by class_id)
																		AND id <> 1
																		ORDER BY id");
			foreach ($classes as $class) {
				//View in templates
				$this->createViewTemplate($class);
			}
		} else {
			print_r('Function only local environment');
		}

		echo "Finish \n";
	}

	/*
	 * Crea un archivo controller
	 */

	public function createController($class) {
		$replace = [];
		$file = [];
		if (!file_exists(app_path() . '/Http/Controllers/Editora/' . $class->name . '.php')) {

			if (file_exists(__DIR__ . '/stubs/EditoraController.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraController.stub');

				$repositoryNamespace = 'App\Http\Controllers\Editora;';
				$replace["DummyNamespace"] = 'App\Http\Controllers\Editora;';
				$replace["DummyModelClass"] = $class->name . 'Model';
				$replace["DummyClass"] = $class->name;
				$replace["DummyLowerCaseClass"] = strtolower($class->name);

				$file = str_replace(array_keys($replace), array_values($replace), $file);

				$file_php = fopen(app_path() . '/Http/Controllers/Editora/' . $class->name . '.php', "w");
				fwrite($file_php, $file);
				fclose($file_php);

				echo("Create " . $class->name . " Controller \n");
			} else {
				echo "Not exist stub controller \n";
			}
		} else {
			echo "Exist " . $class->name . " Controller \n";
		}
	}

	/*
	 * Crea un archivo model de la extraccion de la editora
	 */

	public function createModel($class) {
		$replace = [];
		$file = [];
		if (!file_exists(app_path() . '/Models/' . $class->name . 'Model.php')) {

			if (file_exists(__DIR__ . '/stubs/EditoraModel.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraModel.stub');

				$replace["DummyModelClass"] = $class->name . 'Model';
				$replace["DummyRelations"] = $this->getChildrenRelations($class->class_id);
				$replace["DummyClassID"] = $class->class_id;

				$file = str_replace(array_keys($replace), array_values($replace), $file);

				$file_php = fopen(app_path() . '/Models/' . $class->name . 'Model.php', "w");
				fwrite($file_php, $file);
				fclose($file_php);

				echo("Create " . $class->name . " Model \n");
			} else {
				echo "Not exist stub model \n";
			}
		} else {
			echo "Exist " . $class->name . " Model \n";
		}
	}

	/*
	 * Crea un archivo blade
	 */

	public function createView($class) {
		$replace = [];
		$file = [];
		if (!file_exists(base_path() . '/resources/views/editora/' . strtolower($class->name) . '.blade.php')) {

			if (file_exists(__DIR__ . '/stubs/EditoraView.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraView.stub');

				$file_php = fopen(base_path() . '/resources/views/editora/' . strtolower($class->name) . '.blade.php', "w");
				fwrite($file_php, $file);
				fclose($file_php);

				echo("Create " . $class->name . " View \n");
			} else {
				echo "Not exist stub view \n";
			}
		} else {
			echo "Exist " . $class->name . " View \n";
		}
	}

	/*
	 * Crea un archivo blade en template
	 */

	public function createViewTemplate($class) {
		//Crear VIEW en templates
		$replace = [];
		$file = [];
		if (!file_exists(base_path() . '/resources/views/editora/templates/' . strtolower($class->name) . '.blade.php')) {

			if (file_exists(__DIR__ . '/stubs/EditoraView.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraViewTemplate.stub');

				$file_php = fopen(base_path() . '/resources/views/editora/templates/' . strtolower($class->name) . '.blade.php', "w");
				fwrite($file_php, $file);
				fclose($file_php);

				echo("Create " . $class->name . " View Template\n");
			} else {
				echo "Not exist stub view template\n";
			}
        } else {
            echo "Exist " . $class->name . " View Template\n";
        }
	}

	/*
	 * Prepara el texto de la extraccion para el Model.
	 */

	public function getChildrenRelations($class_id) {

		$relations = DB::table('omp_relations')
				->select('id', 'child_class_id', 'multiple_child_class_id', 'tag')->where('parent_class_id', $class_id)->get();

		$text = '';
		$relation_tag = '';
		foreach ($relations as $relation) {
			$relation_tag .= '$' . $relation->tag . ',';

			$relations_child = [];
			if (strcmp($relation->child_class_id, '0') == 0) {
				$classes_rel = explode(",", $relation->multiple_child_class_id);
				foreach ($classes_rel as $key_rel => $class_rel) {
					$relations_child[$key_rel] = $class_rel;
				}
			} else {
				$relations_child[0] = $relation->child_class_id;
			}

			$query_where = '';
			foreach ($relations_child as $key => $relation_child) {
				if ($key == 0) {
					$query_where .= "where parent_class_id = " . $relation_child;
				} else {
					$query_where .= " OR parent_class_id = " . $relation_child;
				}
			}

			if ($query_where != '') {
				$childs_relation_tag = DB::select("select tag from omp_relations " . $query_where . ' group by tag');
			} else {
				$childs_relation_tag = [];
			}

			if (!empty($childs_relation_tag)) {

				$text .= "\t\t\t$" . $relation->tag . ' = $extractor->findChildrenInstances($i, "' . $relation->tag . '", null, null, function($i) use ($extractor) {' . "\n";

				$child_relation_tag = '';
				foreach ($childs_relation_tag as $child_relation) {
					$child_relation_tag .= '$' . $child_relation->tag . ',';
					$text .= "\t\t\t\t$" . $child_relation->tag . ' = $extractor->findChildrenInstances($i, "' . $child_relation->tag . '", null, null, null);' . "\n";
				}
				$text .= "\t\t\t\treturn array_merge(" . substr($child_relation_tag, 0, -1) . ");\n";
				$text .= "\t\t\t});" . "\n";
			} else {
				$text .= "\t\t\t$" . $relation->tag . ' = $extractor->findChildrenInstances($i, "' . $relation->tag . '", null, null, null);' . "\n";
			}
		}
		if (!empty($relation_tag)) {
			$text .= "\t\t\treturn array_merge(" . substr($relation_tag, 0, -1) . ");";
		}
		return $text;
	}

}
