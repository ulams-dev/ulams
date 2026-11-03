# Lesson file grammar

The normative grammar of a lesson file, implemented in `lesson-file.ts`. The docs page
`front/docs-site/src/content/docs/developers/course-folder-format.mdx` carries the same block (a test keeps them identical).

```ebnf
lesson       = front-matter , body ;
front-matter = "---" , EOL , yaml , ( "---" | "..." ) , EOL ;
body         = { block | loose | blank-line } ;

block        = open-line , content , close-line ;
open-line    = fence , kind , [ attributes ] , EOL ;
close-line   = fence , EOL ;
fence        = ":::" , { ":" } ;               (* the closing fence has exactly as many colons as the opening one *)
kind         = "paragraph" | "callout" | "steps" | "code" | "table" | "example" ;
attributes   = "{" , attribute , { " " , attribute } , "}" ;
attribute    = "#" , id
             | "cite=" , list
             | "origin=" , ( "ai" | "author" )
             | "objectives=" , list ;
list         = '"' , item , { "," , item } , '"' | item ;
item         = id | fragment-id ;
id           = 26 * ( "0".."9" | "a".."z" ) ;
fragment-id  = "frg_" , 12 * ( "a".."z" | "2".."7" ) ;

content      = { line } ;                      (* Markdown, stored verbatim; see the rules below *)
loose        = markdown-chunk ;                (* outside any block, split at blank lines *)
```

Rules the grammar cannot say:

- The front matter must be the first thing in the file. YAML syntax errors are reported at the line of the file.
- A line is an opening line only when it starts at column 1 with three or more colons followed by a block kind.
  A line of only colons closes the block that is open (same colon count) or is a stray closer.
- Fenced code (backticks or tildes) inside a block, and in loose Markdown, is opaque: `:::` lines inside it do not
  close anything, and blank lines inside it do not split loose Markdown.
- Blocks do not nest. An opening line with the same colon count as the open block is reported
  (`DIRECTIVE_NESTED`); a longer fence (`::::`) lets the content hold `:::` lines.
- Block content loses its leading blank lines and trailing whitespace; the rest is kept as written.
- Loose Markdown becomes `paragraph` blocks with `origin: author` and a new id when `ulams course fmt` runs;
  `ulams course validate` reports it as a note (`LOOSE_MARKDOWN`) and treats it the same way.
- Unknown block kinds and unknown attributes are errors. Attribute values are checked (`#id`, fragment ids, `origin`).
