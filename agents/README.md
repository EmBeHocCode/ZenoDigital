# 🤖 Digital-Shop AI Agents

This directory contains specialized AI agents for Digital-Shop, adapted from the [agency-agents](https://github.com/msitarzewski/agency-agents) repository.

Each agent is a prompt template with:
- **Identity & Memory**: Role definition and expertise
- **Core Mission**: Primary responsibilities
- **Technical Deliverables**: Code examples, templates, frameworks
- **Workflow Process**: Step-by-step execution guidance
- **Success Metrics**: Measurable outcomes

## 📁 Structure

```
agents/
├── engineering/          # Code quality, architecture, performance
│   ├── frontend-developer.md
│   ├── backend-architect.md
│   └── code-reviewer.md
├── product/              # Feature prioritization, roadmapping
│   └── product-manager.md
└── sales/                # Conversion optimization, revenue growth
    └── ecommerce-strategist.md
```

## 🚀 Quick Start

1. **Open GitHub Copilot** in your IDE
2. **Reference an agent**: `Activate Frontend Developer and help me build...`
3. **Get specialized assistance** tailored to that role's expertise

### Example Session

```
You: Activate Frontend Developer and build a product filter component with:
- Price range slider (min/max)
- Category checkboxes  
- Real-time product count
- Mobile responsive
- Accessible (keyboard navigation)

Copilot: Here's a TypeScript React component...
```

## 📖 Agent Descriptions

### 🎨 Engineering

#### Frontend Developer
- **Focus**: React/Next.js components, accessibility, performance
- **Use When**: Building UI, optimizing performance, ensuring A11y
- **Output**: Type-safe, accessible, performant React components

#### Backend Architect  
- **Focus**: APIs, databases, security, e-commerce systems
- **Use When**: Designing APIs, schema, payment flows, auth
- **Output**: API specs, database design, security patterns

#### Code Reviewer
- **Focus**: Quality assurance, security, performance optimization
- **Use When**: PR reviews, security audits, performance analysis
- **Output**: Code review feedback with specific fixes

### 📊 Product

#### Product Manager
- **Focus**: Feature prioritization, business metrics, roadmapping
- **Use When**: Planning features, setting metrics, competitive analysis
- **Output**: Prioritization framework, business cases, roadmaps

### 💰 Sales & Marketing

#### E-commerce Strategist
- **Focus**: Conversion optimization, pricing, customer retention
- **Use When**: Copywriting, pricing strategy, email marketing
- **Output**: Product copy, email sequences, pricing analysis

## 💡 Usage Patterns

### Pattern 1: Plan & Execute
```
1. Product Manager → Define feature & success metrics
2. Backend Architect → Design API & database
3. Frontend Developer → Build UI component
4. Code Reviewer → QA
5. E-commerce Strategist → Launch & optimize
```

### Pattern 2: Code Review
```
Code Reviewer → Identify issues
Frontend/Backend Developer → Fix issues
Code Reviewer → Verify fixes
```

### Pattern 3: Optimization
```
E-commerce Strategist → Identify opportunity
Backend Architect → Performance analysis
Frontend Developer → Implement optimization
Code Reviewer → Verify no regressions
```

## ✨ Best Practices

✅ **Be Specific**: Include context, constraints, and goals
✅ **Provide Examples**: Show what good looks like
✅ **Share Context**: Stack details, existing patterns
✅ **Iterate**: Ask follow-up questions and refine
✅ **Verify**: Test suggestions before production

❌ **Don't**: Ask open-ended questions
❌ **Don't**: Skip security review for sensitive code
❌ **Don't**: Over-optimize prematurely
❌ **Don't**: Treat agent suggestions as gospel

## 📚 Learn More

- **Full Usage Guide**: See `../AGENTS.md` for detailed workflows and examples
- **Original Repo**: [agency-agents](https://github.com/msitarzewski/agency-agents) (100+ agents for different domains)
- **GitHub Copilot Docs**: [docs.github.com/copilot](https://docs.github.com/en/copilot)

## 🔧 Customization

These agents are **templates**. Feel free to:
- Add domain-specific examples (your product details, tech stack)
- Create new agents for specialized roles
- Adapt workflows to your team's process
- Share improvements with the team

## 🤝 Contributing

Found a better agent prompt? Created a new one? 
- Create a PR with improvements
- Document what changed and why
- Test with the team before merging

---

**Created**: 2026-03-13
**Source**: [msitarzewski/agency-agents](https://github.com/msitarzewski/agency-agents)
**Adapted For**: Digital-Shop e-commerce platform
