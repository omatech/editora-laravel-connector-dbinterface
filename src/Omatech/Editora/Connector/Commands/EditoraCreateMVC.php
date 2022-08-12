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
	protected $signature = 'editora:createmvc {--include_classes=} {--exclude_classes=} {--old_school_controllers} {--force_overwrite_views} {--force_overwrite_models} {--force_overwrite_controllers} {--force_overwrite_all}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create MVC';
	protected $force_overwrite_views = false;
	protected $force_overwrite_models = false;
	protected $force_overwrite_controllers = false;
	protected $old_school_controllers = false;

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

			if (!empty($this->option('force_overwrite_views'))) {
				$this->force_overwrite_views = true;
			}

			if (!empty($this->option('force_overwrite_models'))) {
				$this->force_overwrite_models = true;
			}

			if (!empty($this->option('old_school_controllers'))) {
				$this->old_school_controllers = true;
			}

			if (!empty($this->option('force_overwrite_controllers'))) {
				$this->force_overwrite_controllers = true;
			}

			if (!empty($this->option('force_overwrite_all'))) {
				$this->force_overwrite_views = true;
				$this->force_overwrite_models = true;
				$this->force_overwrite_controllers = true;
			}

			/* include_class */
			$arguments = '';
			$include_class = '';
			if (!empty($this->option('include_classes'))) {
				$arguments = $this->option('include_classes');
				$classes = explode(",", $arguments);

				foreach ($classes as $key => $class) {
					if ($key == 0) {
						$include_class .= ' AND (id = ' . $class . ' ';
					} else {
						$include_class .= ' OR id = ' . $class . ' ';
					}
				}
				$include_class .= ')';
			}

			/* exclude_class */
			$arguments = '';
			$exclude_class = '';
			if (!empty($this->option('exclude_classes'))) {
				$arguments = $this->option('exclude_classes');
				$exclude_class.=" and id not in ($arguments)";
			}			

			//Classes con NICEURL
			$classes = DB::select("select id as class_id, name from omp_classes
                                        where id in
                                        (select class_id
                                        from omp_class_attributes ca
                                        where ca.atri_id=10002
                                        group by class_id)
                                        AND id <> 1
                                        " . $include_class . "
                                        " . $exclude_class . "
                                        ORDER BY id");

			foreach ($classes as $class) {
				//Controller
				$this->createController($class);
				//Model
				$this->createModel($class);
				//View
				$this->createView($class);
			}

			//Clases sin NICEURL
			$classes = DB::select("select id, name from omp_classes
                                        where id not in
                                        (select class_id
                                        from omp_class_attributes ca
                                        where ca.atri_id=10002
                                        group by class_id)
                                        AND id <> 1
                                        " . $include_class . "
																				" . $exclude_class . "
                                        ORDER BY id");
			foreach ($classes as $class) {
				//View in templates
				$this->createViewTemplate($class);
			}
		} else {
			print_r('Function only available in local environment');
		}

		echo "Finish \n";
	}

	/*
	 * Crea un archivo controller
	 */

	public function createController($class) {
		$replace = [];
		$file = [];

		$stub='/stubs/EditoraController.20220812.stub';
		if ($this->old_school_controllers)
		{
			$stub='/stubs/EditoraController.stub';
		}

		if (!file_exists(app_path() . '/Http/Controllers/Editora/'))
			mkdir(app_path() . '/Http/Controllers/Editora/', 0755, true);

		if (!file_exists(app_path() . '/Http/Controllers/Editora/' . $class->name . '.php') || $this->force_overwrite_controllers) {

			if (file_exists(__DIR__ . $stub)) {
				$file = file_get_contents(__DIR__ . $stub);

				$repositoryNamespace = 'App\Http\Controllers\Editora';
				$replace["DummyNamespace"] = 'App\Http\Controllers\Editora';
				$replace["DummyModelClass"] = $class->name . 'Extraction';
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

		if (!file_exists(app_path() . '/Extractions/'))
			mkdir(app_path() . '/Extractions/', 0755, true);

		if (!file_exists(app_path() . '/Extractions/' . $class->name . 'Extraction.php') || $this->force_overwrite_models) {

			if (file_exists(__DIR__ . '/stubs/EditoraModel.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraModel.stub');

				$replace["DummyModelClass"] = $class->name . 'Extraction';
				$replace["DummyRelations"] = $this->getChildrenRelations($class->class_id);
				$replace["DummyClassID"] = $class->class_id;

				$file = str_replace(array_keys($replace), array_values($replace), $file);

				$file_php = fopen(app_path() . '/Extractions/' . $class->name . 'Extraction.php', "w");
				fwrite($file_php, $file);
				fclose($file_php);

				echo("Create " . $class->name . " Extraction \n");
			} else {
				echo "Not exist stub Extraction \n";
			}
		} else {
			echo "Exist " . $class->name . " Extraction \n";
		}
	}

	/*
	 * Crea un archivo blade
	 */

	public function createView($class) {
		$replace = [];
		$file = [];
		if (!file_exists(base_path() . '/resources/views/pages/'))
			mkdir(base_path() . '/resources/views/pages/', 0755, true);
		if (!file_exists(base_path() . '/resources/views/pages/' . strtolower($class->name) . '.blade.php') || $this->force_overwrite_views) {

			if (file_exists(__DIR__ . '/stubs/EditoraView.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraView.stub');

				$file_php = fopen(base_path() . '/resources/views/pages/' . strtolower($class->name) . '.blade.php', "w");
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
		if (!file_exists(base_path() . '/resources/views/blocks/'))
			mkdir(base_path() . '/resources/views/blocks/', 0755, true);
		if (!file_exists(base_path() . '/resources/views/blocks/' . strtolower($class->name) . '.blade.php') || $this->force_overwrite_views) {

			if (file_exists(__DIR__ . '/stubs/EditoraView.stub')) {
				$file = file_get_contents(__DIR__ . '/stubs/EditoraViewTemplate.stub');

				$file_php = fopen(base_path() . '/resources/views/blocks/' . strtolower($class->name) . '.blade.php', "w");
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
