<?php

declare(strict_types=1);

use Codewithkyrian\Whisper\Whisper;
use Codewithkyrian\Whisper\WhisperFullParams;
use Tkui\Application;
use Tkui\Dialogs\DirectoryDialog;
use Tkui\Dialogs\MessageBox;
use Tkui\Dialogs\OpenFileDialog;
use Tkui\Dialogs\SaveFileDialog;
use Tkui\DotEnv;
use Tkui\Environment;
use Tkui\Image;
use Tkui\Layouts\Pack;
use Tkui\TclTk\TkAppFactory;
use Tkui\Widgets\Buttons\Button;
use Tkui\Widgets\Consts\State;
use Tkui\Widgets\Label;
use Tkui\Widgets\LabelFrame;
use Tkui\Widgets\Widget;
use Tkui\Windows\MainWindow;
use function Codewithkyrian\Whisper\outputSrt;
use function Codewithkyrian\Whisper\readAudio;

require_once 'vendor/autoload.php';

class DemoAppWindow extends MainWindow
{
	const APP_NAME = 'Gerador de Legendas';

	protected Application $app;

	public function __construct(string $title)
	{
		$factory = new TkAppFactory(self::APP_NAME);
		$this->app = $factory->createFromEnvironment(DotEnv::create(dirname(__DIR__)));
		parent::__construct($this->app, $title);
	}

	public function run(): void
	{
		$this->app->run();
	}
}

$demo = new class extends DemoAppWindow
{
	private string $audioFilePath;
	private string $saveDirectoryPath;
	private Button $saveButton;

	public function __construct()
	{
		parent::__construct('Dialogs demo');
		$this->pack([
			$this->createOpenDialogFrame(),
			$this->createChooseDirectoryFrame(),
			$this->saveButton = $this->createSaveButton(),
		], [
			'fill' => Pack::FILL_X, 'padx' => 20, 'pady' => 10,
		]);
	}

	private function createOpenDialogFrame(): Widget
	{
		$openWithFilterButton = new Button($this, 'Selecionar arquivo de áudio');

		$openFileDialog = new OpenFileDialog($this, ['title' => 'Choose a file']);
		$openFileDialog->addFileType('Audio Files', ['.mp3', '.wav', '.ogg', '.flac']);
		$openFileDialog->addFileType('All files', '*');

		$openFileDialog->onSuccess(function (string $file): void {
			if (file_exists($file)) {
				$this->audioFilePath = $file;
				return;
			}

			$encodedFile = mb_convert_encoding($file, 'Windows-1252', 'UTF-8');
			if (file_exists($encodedFile)) {
				$this->audioFilePath = $encodedFile;
			}
		});

		$openWithFilterButton->onClick($openFileDialog->showModal(...));

		return $openWithFilterButton;
	}

	private function createChooseDirectoryFrame(): Widget
	{
		$choseDirectoryButton = new Button($this, 'Escolher pasta');

		$directoryDialog = new DirectoryDialog($this, ['title' => 'Directory']);
		$directoryDialog->onSuccess(function (string $dir): void {
			if (is_dir($dir)) {
				$this->saveDirectoryPath = $dir;
				return;
			}

			$encodedDir = mb_convert_encoding($dir, 'Windows-1252', 'UTF-8');
			if (is_dir($encodedDir)) {
				$this->saveDirectoryPath = $encodedDir;
			}
		});

		$choseDirectoryButton->onClick($directoryDialog->showModal(...));

		return $choseDirectoryButton;
	}

	private function createSaveButton(): Button
	{
		$saveButton = new Button($this, 'Gerar legenda');
		$saveButton->onClick(function () {
			$directoryExists = isset($this->saveDirectoryPath) && is_dir($this->saveDirectoryPath);
			$audioFileExists = isset($this->audioFilePath) && file_exists($this->audioFilePath);

			if (!$directoryExists || !$audioFileExists) {
				$alert = new MessageBox($this,  'Erro', 'Selecione um diretório e um arquivo de áudio', [
					'icon' => MessageBox::ICON_ERROR,
				]);
				$alert->showModal();
				return;
			}

			$params = WhisperFullParams::default()
				->withNThreads(8);

			$audio = readAudio($this->audioFilePath);

			$whisper = Whisper::fromPretrained('medium', __DIR__ . '/models', $params);

			$segments = $whisper->transcribe($audio, 8);

			outputSrt($segments, $this->saveDirectoryPath . '/legendas.srt');
		});

		return $saveButton;
	}
};

$demo->run();

