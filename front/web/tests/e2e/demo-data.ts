/**
 * The demo tenants are reseeded (hourly reset, seeder changes), so ids are looked up from the
 * tenant API instead of being hard-coded: the course of each tenant and one topic per type.
 */
const api = (slug: string) => process.env[`API_${slug.toUpperCase()}`] ?? `http://${slug}.localhost`;

interface Topic {
  id: number;
  preview?: boolean;
  topicable_type: string;
}

export interface DemoCourse {
  courseId: number;
  /** topicable class name (Video, GiftQuiz, ...) → first topic id of that type */
  topics: Record<string, number>;
  /** first free-preview topic */
  previewTopic: number | null;
}

const cache = new Map<string, Promise<DemoCourse>>();

export function demoCourse(slug: string): Promise<DemoCourse> {
  let running = cache.get(slug);
  if (!running) {
    running = (async () => {
      const list = (await (await fetch(`${api(slug)}/api/courses`, { headers: { Accept: "application/json" } })).json()) as { data: Array<{ id: number }> };
      const courseId = list.data[0]!.id;
      const detail = (await (await fetch(`${api(slug)}/api/courses/${courseId}`, { headers: { Accept: "application/json" } })).json()) as {
        data: { lessons: Array<{ topics: Topic[] }> };
      };
      const topics: Record<string, number> = {};
      let previewTopic: number | null = null;
      for (const lesson of detail.data.lessons) {
        for (const topic of lesson.topics) {
          const kind = topic.topicable_type.split("\\").pop() ?? "";
          if (!(kind in topics)) topics[kind] = topic.id;
          if (topic.preview && previewTopic === null) previewTopic = topic.id;
        }
      }
      return { courseId, topics, previewTopic };
    })();
    cache.set(slug, running);
  }
  return running;
}
