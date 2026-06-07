## naming
- always prefix `mike_` or `mike-` if you feel this can be conflict (with other plugins, wordpress)
- for `__( 'Hello', 'mike' )` always use `esc_htm__( 'Hello', 'mike' )` instead


## TOC
- you must build TOC of files you code. The TOC looks like this:

```
1. mike_func_1 - what it does, one sentence
2. mike_func_2 - what it does, one sentence
3. Footer - no explaination if not needed
4. Sidebar
```

then at the function, use comment style like this:
```
/* 2. mike_func_1 - explain shortly here
------------------------------------------------------ */
```

when you have number at header TOC, you have corresponding number at position the TOC pointed to

- the TOC must be full list of things in that file, and the developer can understand the file by looking it, even 10 months from now

## coding style
- **preference: easier to audit, is super important**: whenever you decide something, think: is that easy to audit? For instance, create new `subdir/subdir/func.php`? nope, because developer needs to open subdir, then open it, then open func.php, then find the function `the_fun_i_can_write_inline_easily()`. Do not do that.
- **HARD RULE, NEVER BREAK IT**: always code the way some one have just learned the code 3 weeks (sometimes, 3 months) can understand. For instance:
    + css: don't use complicated rules, padding-inline, padding-block? probably nope. simply: `padding-top: 10px; padding-bottom: 10px;` instead of using `padding-block`
    + php: don't use class, never, unless you must
    + php: if something doesn't require function, never invent another function. **always ask: does this task needs a function? if not, don't create it.** why? because developer doesn't want to go check another function to audit the code.
- value long, repeat but auditable, over creative, but more time to audit.
- use `entry-content, entry-categories` wp class style, not BEM class stuyle
- comments: one line, max 10 words. only very rare exceptions

## css section comments
- big sections: a long `----` line under the title
- small sections inside a big one: a medium `---` line under the title

```
/* header
-------------------------------------------- */

/* header menu
------------------- */
```