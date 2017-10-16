<?php

namespace RS\NView\Compilers;

class BladeCompiler extends \Illuminate\View\Compilers\BladeCompiler
{
    /**
     * Compile the include statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileNview($expression)
    {
        $expression = $this->stripParentheses($expression);

        return "<?php echo view({$expression}, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";
    }


}