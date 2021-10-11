<?php

	namespace Inteve\AssetsManager;

	use CzProject\Assert\Assert;
	use Nette\Utils\Html;
	use Nette\Utils\Validators;


	class AssetsManager
	{
		/** @var string */
		private $publicBasePath;

		/** @var string */
		private $environment;

		/** @var AssetFiles */
		private $assetFiles;

		/** @var Bundler|NULL */
		private $bundler;

		/** @var IFileHashProvider|NULL */
		private $fileHashProvider;


		/**
		 * @param  string $environment
		 * @param  string $publicBasePath
		 * @param  IAssetsBundle[] $assetsBundles
		 */
		public function __construct(
			$environment,
			$publicBasePath = '',
			array $assetsBundles = [],
			IFileHashProvider $fileHashProvider = NULL
		)
		{
			Assert::string($publicBasePath);
			Assert::string($environment);

			$this->publicBasePath = $publicBasePath;
			$this->environment = $environment;
			$this->bundler = !empty($assetsBundles) ? new Bundler($assetsBundles) : NULL;
			$this->fileHashProvider = $fileHashProvider;
			$this->assetFiles = new AssetFiles;
		}


		/**
		 * @param  string|AssetFile $path
		 * @return string
		 */
		public function getPath($path)
		{
			if ($path instanceof AssetFile) {
				$path = $path->getPath();
			}

			if (Validators::isUrl($path)) {
				return $path;
			}

			if ($this->fileHashProvider !== NULL && $this->canUseFileHashProvider($path)) {
				$hash = $this->fileHashProvider->getFileHash($path);

				if ($hash !== NULL) {
					$pathInfo = pathinfo($path);

					if (isset($pathInfo['extension'])) {
						$path = ($pathInfo['dirname'] !== '.' ? ($pathInfo['dirname'] . '/') : '') . $pathInfo['filename'] . '.' . $hash . '.' . $pathInfo['extension'];
						return rtrim($this->publicBasePath, '/') . '/' . $path;
					}
				}
			}

			return rtrim($this->publicBasePath, '/') . '/' . $path;
		}


		/**
		 * @param  string $name
		 * @return void
		 */
		public function requireBundle($name)
		{
			if ($this->bundler === NULL) {
				throw new InvalidStateException('No bundles.');
			}

			$this->bundler->requireBundle($name);
		}


		/**
		 * @param  string $file
		 * @param  string|NULL $environment
		 * @return void
		 */
		public function addStylesheet($file, $environment = NULL)
		{
			$this->assetFiles->addStylesheet($file, $environment);
		}


		/**
		 * @param  string $file
		 * @param  string|NULL $environment
		 * @return void
		 */
		public function addScript($file, $environment = NULL)
		{
			$this->assetFiles->addScript($file, $environment);
		}


		/**
		 * @param  string $file
		 * @param  string|NULL $environment
		 * @return void
		 */
		public function addCriticalScript($file, $environment = NULL)
		{
			$this->assetFiles->addCriticalScript($file, $environment);
		}


		/**
		 * @return AssetFile[]
		 */
		public function getStylesheets()
		{
			$result = $this->bundler !== NULL ? $this->bundler->getStylesheets($this->environment) : [];

			foreach ($this->assetFiles->getStylesheets($this->environment) as $file) {
				$result[] = $file;
			}

			return $this->removeDuplicates($result);
		}


		/**
		 * @return AssetFile[]
		 */
		public function getScripts()
		{
			$result = $this->bundler !== NULL ? $this->bundler->getScripts($this->environment) : [];

			foreach ($this->assetFiles->getScripts($this->environment) as $file) {
				$result[] = $file;
			}

			return $this->removeDuplicates($result);
		}


		/**
		 * @return AssetFile[]
		 */
		public function getCriticalScripts()
		{
			$result = $this->bundler !== NULL ? $this->bundler->getCriticalScripts($this->environment) : [];

			foreach ($this->assetFiles->getCriticalScripts($this->environment) as $file) {
				$result[] = $file;
			}

			return $this->removeDuplicates($result);
		}


		/**
		 * @return Html[]
		 */
		public function getStylesheetsTags()
		{
			$tags = [];

			foreach ($this->getStylesheets() as $file) {
				$rel = 'stylesheet';

				if ($file->isOfType('less')) {
					$rel = 'stylesheet/less';
				}

				$tags[] = Html::el('link')
					->rel($rel)
					->type('text/css')
					->href($this->getPath($file));
			}

			return $tags;
		}


		/**
		 * @return Html[]
		 */
		public function getScriptsTags()
		{
			$tags = [];

			foreach ($this->getScripts() as $file) {
				$tags[] = Html::el('script')->src($this->getPath($file));
			}

			return $tags;
		}


		/**
		 * @return Html[]
		 */
		public function getCriticalScriptsTags()
		{
			$tags = [];

			foreach ($this->getCriticalScripts() as $file) {
				$tags[] = Html::el('script')->src($this->getPath($file));
			}

			return $tags;
		}


		/**
		 * @param  AssetFile[] $files
		 * @return AssetFile[]
		 */
		private function removeDuplicates(array $files)
		{
			$result = [];
			$usedPaths = []; // path => TRUE

			foreach ($files as $file) {
				$path = $file->getPath();

				if (isset($usedPaths[$path])) {
					continue;
				}

				$result[] = $file;
				$usedPaths[$path] = TRUE;
			}

			return $result;
		}


		/**
		 * @param  string $path
		 * @return bool
		 */
		private function canUseFileHashProvider($path)
		{
			$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			return in_array($extension, [
				'js',
				'css',
				'less',
			], TRUE);
		}
	}
