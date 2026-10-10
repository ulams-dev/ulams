---
title: 7. Fermi–Pasta–Ulam–Tsingou
summary: One of the first experiments done on a computer, a surprise, and the name of the programmer that was added in 2008.
duration: 30 min
---

::: richtext title="7.1 The first numerical experiment" duration="10 min" intro="What Fermi, Pasta, Ulam and Tsingou tried, what surprised them and who programmed it."
In May 1955 the Los Alamos report LA-1940, "Studies of Nonlinear Problems", described a numerical experiment on a computer. The report says that the work was done by Fermi, Pasta, Ulam and Tsingou, and that it was written by Fermi, Pasta and Ulam {{src:U-24}}. Mary Tsingou, later Menzel, born in 1928, programmed the computation on the MANIAC I {{src:U-24}}.

The scientists expected the energy to spread over all the modes of the system. Instead, after a longer run, "almost all the energy was back to the lowest frequency mode" {{src:U-24}}. This return is called the FPU recurrence.

Enrico Fermi died on 28 November 1954, before the report was written up, and it was never published in a journal {{src:U-60,U-24}}. In 1965 Norman Zabusky and Martin Kruskal linked the recurrence to solitons {{src:U-46,U-24}}. In 2008 Thierry Dauxois proposed calling it the Fermi–Pasta–Ulam–Tsingou problem, so that the name of the programmer is part of its name {{src:U-24}}.

What the story shows is that a computer can be used for an experiment, not only for a sum. The interactive below is not a simulation of the FPUT system. It only lets you run a very simple rule and watch what a program draws.
:::

::: interactive title="7.1 A program as an experiment" package=ulam-automaton start=rule30 end=rule30 display=inline height=640 duration="4 min"
A computer is told a simple rule and left to run. You cannot tell from the rule alone what picture comes out: you have to run it. That is the idea of an experiment done on a computer.

**Try this.** Press Step ten times, then Run to the end. Could you have predicted the picture from the rule?
:::

::: layout title="Timeline: from Fermi's death to the new name" duration="5 min" sources=U-60,U-24,U-46
{
  "document": [
    {"component": "Timeline", "props": {
      "title": "The FPUT story",
      "intro": "From the death of Fermi to the name of the problem.",
      "items": [
        {"label": "28 Nov 1954", "title": "Fermi dies", "text": "He dies before the report is written up."},
        {"label": "May 1955", "title": "Report LA-1940", "text": "\"Studies of Nonlinear Problems\" is written by Fermi, Pasta and Ulam; it says the work was done by Fermi, Pasta, Ulam and Tsingou. It is never published in a journal."},
        {"label": "1965", "title": "Zabusky and Kruskal", "text": "They link the recurrence to solitons (Physical Review Letters 15)."},
        {"label": "2008", "title": "A new name", "text": "Thierry Dauxois proposes calling it the Fermi–Pasta–Ulam–Tsingou problem."}
      ]
    }}
  ],
  "fallback": "## The FPUT story\n\n- 28 November 1954: Fermi dies.\n- May 1955: report LA-1940, written by Fermi, Pasta and Ulam; the work was done by Fermi, Pasta, Ulam and Tsingou.\n- 1965: Zabusky and Kruskal link the recurrence to solitons.\n- 2008: Thierry Dauxois proposes the name Fermi–Pasta–Ulam–Tsingou."
}
:::

::: quiz title="Quiz: Fermi–Pasta–Ulam–Tsingou" duration="5 min" pass=60
// facts: 8.2, 8.3, 8.6, 8.7 [U-24][U-46]
::M7-Q1::Match each person to their part in the story. {
  =Mary Tsingou -> programmed the computation on the MANIAC I
  =Zabusky and Kruskal -> linked the recurrence to solitons in 1965
  =Thierry Dauxois -> proposed adding Tsingou's name in 2008
  =Fermi, Pasta and Ulam -> wrote the 1955 report LA-1940
}

// facts: 8.4 [U-24]
::M7-Q2::In the experiment the energy spread evenly over all the modes, as the scientists expected. {F}

// facts: 8.3 [U-24]
::M7-Q3::On which computer did Mary Tsingou run the calculation? {
  =MANIAC I
  ~ENIAC
  ~UNIVAC I
  ~IBM 701
}

// facts: 8.1 [U-24]
::M7-Q4::In which year is the Los Alamos report LA-1940, "Studies of Nonlinear Problems", dated? {#1955:0}
:::
