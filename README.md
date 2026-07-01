# Collatz Parity Vector Toolkit

**Version 1.0** | Author: Kazunobu Hikawa, Professor Emeritus, Kanazawa Gakuin University

---

## Overview

The **Collatz Parity Vector Toolkit** is a collection of interactive web-based
software tools for exploring the structural and geometric properties of Parity
Vectors (PVs) in the Collatz Conjecture.

This toolkit accompanies the research paper:

> *"Discrete Geometry and Combinatorial Structure of Parity Vectors
> in the Collatz Map"*
> — Kazunobu Hikawa, 2026

The tools provide computational verification, statistical counting,
sequence visualization, and numerical experiments based on efficient
dynamic programming algorithms. Both a **Python / Flask** version and
a **PHP / XAMPP** version are included, each offering identical functionality.

---

## Background

The Collatz Conjecture states that for any positive integer n, repeatedly
applying the rule

- **if n is even**: divide by 2
- **if n is odd**: multiply by 3, add 1, then divide by 2

will eventually reach 1. Despite its simple formulation, the conjecture
remains one of the most famous unsolved problems in mathematics.

This toolkit approaches the conjecture through the lens of **Parity Vectors**
— binary strings that encode the sequence of odd/even steps — and analyzes
their combinatorial and geometric structure using the concepts of
**Glide**, **Hamming weight**, **convergence**, and **parity vector length**.

---

## Key Features

| Tool | Description |
|---|---|
| **Unified Launcher** | Single-entry-point application combining all five tools with a navigation menu |
| **Counting by Length** | Counts converged and unconverged Parity Vectors for each bit length k, with high-precision ratio output |
| **Counting by Hamming Weight** | Counts converged and unconverged Parity Vectors for each Hamming weight d |
| **Collatz Calculator** | Computes the full Collatz sequence for any large natural number, reporting each step, the Glide value, and the Parity Vector |
| **PV Graph (from PV string)** | Displays a graphical path matrix for a manually entered Parity Vector |
| **PV Graph (from Number)** | Automatically computes the Parity Vector for a given natural number N and displays it graphically |

---

## Implementation

- **Python version** — Built with Flask; runs as a local web application.
  Requires Python 3.8+ and Flask. Uses Python's built-in arbitrary-precision
  integers and the `decimal` module for high-precision ratio computation.

- **PHP version** — Built for XAMPP (Apache + PHP); runs as a server-side
  web application. Requires PHP 8.1+ with the GMP extension enabled for
  arbitrary-precision arithmetic.

Both versions produce identical results and share the same visual design.

---

## Repository Structure