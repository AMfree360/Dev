# Should We Combine Monte Carlo Test Results?

## The Question

If tests answer different questions, is there a need to combine them?

## Short Answer

**For interpretation: NO** - Always interpret tests separately.

**For pass/fail thresholds: YES** - But with clear warnings and individual results always visible.

## What Each Test Measures

| Test | Question | What It Tests |
|------|----------|---------------|
| **Permutation** | Does trade order matter? | Compounding effects, sequencing |
| **Bootstrap** | Does strategy work under resampled markets? | Market structure robustness |
| **Randomized Entry** | Does entry timing contribute to edge? | Entry logic effectiveness |

## What Metrics Are Combined?

All three tests calculate the **same metrics**:
- `final_pnl`: Total profit/loss
- `sharpe_ratio`: Risk-adjusted returns  
- `profit_factor`: Gross profit / Gross loss

### How They're Combined:

1. **For each metric** (final_pnl, sharpe_ratio, profit_factor):
   ```
   metric_score = 0.5 * (1 - p_value) + 0.5 * (percentile / 100)
   ```

2. **For each test** (Permutation, Bootstrap, Randomized Entry):
   ```
   test_score = average(metric_scores for all 3 metrics)
   ```

3. **Combined score**:
   ```
   combined_score = weighted_average(test_scores)
     Weights: Randomized Entry: 50%, Bootstrap: 30%, Permutation: 20%
   ```

## Example: Your Current Results

### Individual Test Scores:

**Bootstrap Test:**
- final_pnl: p=0.10, percentile=90% → score = 0.90
- sharpe_ratio: p=0.00, percentile=100% → score = 1.00
- profit_factor: p=0.00, percentile=100% → score = 1.00
- **Bootstrap test score = (0.90 + 1.00 + 1.00) / 3 = 0.967** ✅

**Randomized Entry Test:**
- final_pnl: p=1.00, percentile=0% → score = 0.00
- sharpe_ratio: p=1.00, percentile=0% → score = 0.00
- profit_factor: p=1.00, percentile=0% → score = 0.00
- **Randomized Entry test score = (0.00 + 0.00 + 0.00) / 3 = 0.00** ❌

### Combined Score:

With 2 tests (Bootstrap + Randomized Entry):
- Normalized weights: Bootstrap = 30/(30+50) = 37.5%, Randomized Entry = 50/(30+50) = 62.5%
- Combined = 0.375 * 0.967 + 0.625 * 0.00 = **0.362**

### What This Means:

- **Bootstrap (0.967)**: Exits and risk management work excellently
- **Randomized Entry (0.00)**: Entry logic is hurting performance
- **Combined (0.362)**: Meaningless - masks the critical finding

## Industry Practice

### What Quant Funds Do:

1. **Report tests separately** - Always show individual results
2. **Provide interpretation** - Explain what each test means
3. **Use combined scores sparingly** - Only for:
   - Automated pass/fail thresholds
   - Comparing multiple strategies (tie-breaker)
   - Quick screening (but always review individual results)

### What They DON'T Do:

- ❌ Hide individual results behind combined score
- ❌ Use combined score as primary interpretation
- ❌ Combine without context

## Recommendation

### Keep Combined Score BUT:

1. ✅ **Always show individual test results first**
2. ✅ **Always show interpretation of each test**
3. ✅ **Show breakdown of what's being combined**
4. ✅ **Add clear warnings** that tests measure different things
5. ✅ **Use combined score only for pass/fail threshold**
6. ✅ **Never hide individual results**

### Display Format:

```
COMBINED ROBUSTNESS SCORE (Weighted Average - For Pass/Fail Only):
  ⚠️  WARNING: Tests measure different things. This score masks critical insights.
  ⚠️  Always interpret individual test results above, not this combined score.
  ⚠️  Use combined score ONLY for automated pass/fail threshold.
  
  Score: 0.362, Percentile: 36.2%, P-value: 0.6375 ✗ NOT ROBUST
  
  Score Breakdown:
    Bootstrap: 0.967 (weight: 37.5%)
    Randomized Entry: 0.000 (weight: 62.5%)
  
  Metrics Combined:
    - final_pnl, sharpe_ratio, profit_factor
    Formula: metric_score = 0.5*(1-p_value) + 0.5*(percentile/100)
    Test score = average(metric_scores)
    Combined = weighted_average(test_scores)
```

## When Combined Score Is Useful

### ✅ Good Use Cases:

1. **Automated Validation**: Single pass/fail threshold
2. **Comparing Strategies**: Quick ranking (but review individual results)
3. **Screening**: Filter out clearly bad strategies

### ❌ Bad Use Cases:

1. **Primary Interpretation**: Always look at individual tests
2. **Hiding Individual Results**: Never hide what's being combined
3. **Masking Conflicts**: When tests disagree, combined score is meaningless

## Conclusion

**Combined scores are useful for automation, but interpretation should always focus on individual test results.**

Your current results show:
- ✅ Bootstrap: Excellent (exits/risk work)
- ❌ Randomized Entry: Critical issue (entry logic hurts)
- ⚠️ Combined: Meaningless (masks the critical finding)

**Action:** Focus on fixing entry logic, not the combined score.

