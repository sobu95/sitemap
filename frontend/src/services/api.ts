export interface DomainItem {
  domain: string;
  status: string;
  pages: number;
}

export async function fetchDomains(): Promise<DomainItem[]> {
  const res = await fetch('/api/getDomains.php');
  return res.json();
}

export async function fetchTrends(domain: string): Promise<number[]> {
  const res = await fetch(`/api/getDomainHistory.php?id=${encodeURIComponent(domain)}`);
  const data = await res.json();
  // history endpoint returns objects with checked_at and result
  return data.history.map((h: { result: number }) => h.result);
}
