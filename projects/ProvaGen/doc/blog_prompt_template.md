# Blog Post Generation Prompt — Prof-Aid

Use this template to ask an LLM to generate SEO-friendly educational blog posts in Markdown that can be imported directly to the Prof-Aid blog.

Instructions for the model:

- Produce a Markdown document with the following frontmatter metadata at the top (YAML):

```
---
title: ""
slug: ""
excerpt: "" # 150-200 characters
tags: ["education", "assessment"]
published: false
---
```

- Body requirements:
  - H1 title must match `title` in frontmatter (single `#`)
  - Include an introductory paragraph (60–120 words) summarizing the article
  - Use subheadings (`##`) for sections; prefer 3–6 sections
  - Include at least one `###` subsection with a short actionable checklist
  - Add a `Conclusion` section summarizing the key takeaways
  - Include an `SEO suggestions` block at the end with:
    - `meta_description` (max 160 chars)
    - `og_image_alt` (alt text for OG image)

- Formatting:
  - Use lists and short paragraphs for readability
  - Include example code blocks or sample items where relevant
  - No raw HTML in content; output must be valid Markdown

- Tone and audience:
  - Tone: professional, helpful, concise
  - Audience: teachers and assessment authors

- Example prompt to the generator (fill fields):

```
Write a 800–1,200 word educational blog post about "How to design multiple-choice questions that test reasoning". Target audience: high school teachers. Provide an SEO-friendly title and a short meta description. Include a checklist with 5 practical tips.

Respond with a Markdown file including the YAML frontmatter as specified above.
```

When the generated Markdown is received by the admin importer, the system should:
- Validate the YAML frontmatter (title, slug, excerpt)
- Store the `markdown` column and optionally render `html` on-demand or during publish
- Set `published` based on frontmatter or admin action

Save this file and use it when asking the content generator to produce posts for import.
