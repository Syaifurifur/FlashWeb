export const volleyballBestOfOptions = [1, 3, 5]

export const normalizeVolleyballSets = (bestOf, scores = []) => {
  const values = Array.isArray(scores) ? scores : []
  return Array.from({length: Number(bestOf) || 0}, (_, index) => ({
    score_a: values[index]?.score_a ?? '',
    score_b: values[index]?.score_b ?? '',
    completed: Boolean(values[index]?.completed),
  }))
}

export const volleyballSummary = (bestOf, scores = []) => {
  const values = Array.isArray(scores) ? scores : []
  const requiredWins = Math.floor((Number(bestOf) || 1) / 2) + 1
  let winsA = 0, winsB = 0, decidedAt = -1
  values.slice(0, Number(bestOf) || 0).forEach((set, index) => {
    if (!set.completed || set.score_a === '' || set.score_b === '' || Number(set.score_a) === Number(set.score_b) || decidedAt >= 0) return
    if (Number(set.score_a) > Number(set.score_b)) winsA += 1
    else winsB += 1
    if (winsA >= requiredWins || winsB >= requiredWins) decidedAt = index
  })
  return {requiredWins, winsA, winsB, decidedAt, decided:decidedAt >= 0}
}
