<?php

declare(strict_types=1);

namespace Henrik\View;

use Closure;
use Henrik\View\Extension\ExtensionInterface;
use RuntimeException;
use Throwable;

final class Renderer
{
    /**
     * @var string path to the root directory of views
     */
    private string $viewDirectory;

    /**
     * @var string file extension of the default views
     */
    private string $fileExtension;

    /**
     * @var string|null name of the view layout
     */
    private ?string $layout = null;

    /**
     * @var string|null name of the block currently being rendered
     */
    private ?string $blockName = null;

    /**
     * @var array<string, string|bool> of blocks content
     */
    private array $blocks = [];

    /**
     * @var array<string, mixed> global variables that will be available in all views
     */
    private array $globalVars = [];

    /**
     * @var array<string, ExtensionInterface>
     */
    private array $extensions = [];

    /**
     * @var Closure
     */
    private Closure $renderer;

    /**
     * @param string|null $viewDirectory path to the root directory of views
     * @param string      $fileExtension file extension of the default views
     */
    public function __construct(?string $viewDirectory = null, string $fileExtension = 'php')
    {
        if (is_string($viewDirectory)) {
            $this->setViewDirectory($viewDirectory);
        }
        $this->setFileExtension($fileExtension);

        $this->renderer = function (string $template, array $parameters): void {
            extract($parameters);

            require $template;
        };
    }

    /**
     * Magic method used to call extension functions.
     *
     * @param string       $name      function name
     * @param array<mixed> $arguments function arguments
     *
     * @throws RuntimeException if the extension or function was not added
     *
     * @return mixed result of the function
     */
    public function __call(string $name, array $arguments)
    {
        foreach ($this->extensions as $extension) {
            foreach ($extension->getFunctions() as $function => $callback) {
                if ($function === $name) {
                    return ($callback)(...$arguments);
                }
            }
        }

        throw new RuntimeException(sprintf('Calling an undefined function "%s".', $name));
    }

    /**
     * Adds an extension.
     *
     * @param ExtensionInterface $extension
     */
    public function addExtension(ExtensionInterface $extension): void
    {
        $this->extensions[get_class($extension)] = $extension;
    }

    /**
     * Adds a global variable.
     *
     * @param string $name  variable name
     * @param mixed  $value variable value
     *
     * @throws RuntimeException if this global variable has already been added
     */
    public function addGlobal(string $name, mixed $value): void
    {
        if (array_key_exists($name, $this->globalVars)) {
            throw new RuntimeException(sprintf(
                'Unable to add "%s" as this global variable has already been added.',
                $name
            ));
        }

        $this->globalVars[$name] = $value;
    }

    /**
     * @param string $layout name of the view layout
     */
    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Records a block.
     *
     * @param string      $name    block name
     * @param string|bool $content block content
     */
    public function block(string $name, bool|string $content): void
    {
        if ($name === 'content') {
            throw new RuntimeException('The block name "content" is reserved.');
        }

        if (!$name || array_key_exists($name, $this->blocks)) {
            return;
        }

        $this->blocks[$name] = $content;
    }

    /**
     * Begins recording a block.
     *
     * @param string $name block name
     *
     * @throws RuntimeException if you try to nest a block in other block
     *
     * @see block()
     */
    public function beginBlock(string $name): void
    {
        if ($this->blockName) {
            throw new RuntimeException('You cannot nest blocks within other blocks.');
        }

        $this->blockName = $name;
        ob_start();
    }

    /**
     * Ends recording a block.
     *
     * @throws RuntimeException if you try to end a block without beginning it
     *
     * @see block()
     */
    public function endBlock(): void
    {
        if ($this->blockName === null) {
            throw new RuntimeException('You must begin a block before can end it.');
        }

        $this->block($this->blockName, ob_get_clean());
        $this->blockName = null;
    }

    /**
     * Renders a block.
     *
     * @param string $name    block name
     * @param string $default default content
     *
     * @return string|bool block content
     */
    public function renderBlock(string $name, string $default = ''): bool|string
    {
        return $this->blocks[$name] ?? $default;
    }

    /**
     * Renders a view.
     *
     * @param string               $view   view name
     * @param array<string, mixed> $params view variables (`name => value`)
     *
     * @throws Throwable if an error occurred during rendering
     *
     * @return string|bool rendered view content
     */
    public function render(string $view, array $params = []): bool|string
    {
        $view = $this->viewDirectory . '/' . trim($view, '\/');

        if (pathinfo($view, PATHINFO_EXTENSION) === '') {
            $view .= ($this->fileExtension ? '.' . $this->fileExtension : '');
        }

        if (!file_exists($view) || !is_file($view)) {
            throw new RuntimeException(sprintf(
                'View file "%s" does not exist or is not a file.',
                $view
            ));
        }

        $level        = ob_get_level();
        $this->layout = null;
        ob_start();

        try {
            ($this->renderer)($view, array_merge($params, $this->globalVars));
            $content = ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        if (!$this->layout) { // @phpstan-ignore-line
            return $content;
        }

        $this->blocks['content'] = $content; // @phpstan-ignore-line

        return $this->render($this->layout);
    }

    /**
     * Escapes special characters, converts them to corresponding HTML entities.
     *
     * @param string $content content to be escaped
     *
     * @return string escaped content
     */
    public function esc(string $content): string
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }

    public function getViewDirectory(): string
    {
        return $this->viewDirectory;
    }

    public function setViewDirectory(string $viewDirectory): void
    {
        $viewDirectory = rtrim($viewDirectory, '\/');

        if (!is_dir($viewDirectory)) {
            throw new RuntimeException(sprintf(
                'The specified view directory "%s" does not exist.',
                $viewDirectory
            ));
        }
        $this->viewDirectory = $viewDirectory;

    }

    public function setFileExtension(string $fileExtension = 'php'): void
    {
        if ($fileExtension && $fileExtension[0] === '.') {
            $fileExtension = ltrim($fileExtension, '.');
        }
        $this->fileExtension = $fileExtension;
    }
}