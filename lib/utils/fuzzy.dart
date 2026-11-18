int levenshtein(String s, String t) {
  if (s == t) return 0;
  if (s.isEmpty) return t.length;
  if (t.isEmpty) return s.length;

  List<List<int>> dp = List.generate(
    s.length + 1,
    (_) => List.filled(t.length + 1, 0),
  );

  for (int i = 0; i <= s.length; i++) {
    dp[i][0] = i;
  }
  for (int j = 0; j <= t.length; j++) {
    dp[0][j] = j;
  }

  for (int i = 1; i <= s.length; i++) {
    for (int j = 1; j <= t.length; j++) {
      int cost = s[i - 1] == t[j - 1] ? 0 : 1;

      dp[i][j] = [
        dp[i - 1][j] + 1,
        dp[i][j - 1] + 1,
        dp[i - 1][j - 1] + cost,
      ].reduce((a, b) => a < b ? a : b);
    }
  }

  return dp[s.length][t.length];
}
